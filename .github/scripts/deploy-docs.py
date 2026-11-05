#!/usr/bin/env python3
"""Upload the built docs site to the docs server over FTP, and resume after a failure.

    python3 .github/scripts/deploy-docs.py SITE_DIR [--dry-run]

Settings come from the environment: FTP_SERVER, FTP_USERNAME, FTP_PASSWORD,
FTP_PROTOCOL (ftp or ftps, default ftps), FTP_PORT (default 21) and FTP_DIR
(default ./).

Like SamKirkland/FTP-Deploy-Action, which deployed the site before, it keeps
a list of the files on the server, with their SHA-256, in
.ftp-deploy-sync-state.json on the server, and uploads only files whose
content changed. It reads and writes the same file in the same format, so
the switch needs no full upload.

Unlike the action, it saves that list while it works, so a deploy that stops
halfway (the server's data connections time out now and then) continues where
it stopped when the job runs again. A dropped connection is also retried
right away, a few times, before the deploy gives up.

Order: new and changed assets first, then the HTML pages that load them, then
the deletion of files that are no longer in the site. A visitor never gets a
page whose scripts are not there yet. Only files listed in the state file
are ever deleted, so other files in the folder (.htaccess, for example) stay.
"""

import ftplib
import hashlib
import io
import json
import os
import posixpath
import sys
import time

STATE_NAME = ".ftp-deploy-sync-state.json"
STATE_DESCRIPTION = (
    "DO NOT DELETE THIS FILE. This file is used to keep track of which files have been synced "
    "in the most recent deployment. If you delete this file a resync will need to be done "
    "(which can take a while) - read more: https://github.com/SamKirkland/FTP-Deploy-Action"
)
STATE_VERSION = "1.0.0"

# Save the state after this many changes, so a stopped deploy loses little.
SAVE_EVERY = 20
# Tries per file before the deploy gives up; a run of the job resumes it.
ATTEMPTS = 6
TIMEOUT = 60


def log(message):
    print(message, flush=True)


def sha256(path):
    digest = hashlib.sha256()
    with open(path, "rb") as file:
        for chunk in iter(lambda: file.read(1 << 16), b""):
            digest.update(chunk)
    return digest.hexdigest()


def local_records(site):
    """The site's folders and files, as the state file lists them."""
    records = {}

    for root, dirs, files in os.walk(site):
        dirs.sort()
        relative = os.path.relpath(root, site).replace(os.sep, "/")
        prefix = "" if relative == "." else relative + "/"

        for name in dirs:
            records[prefix + name] = {"type": "folder", "name": prefix + name}

        for name in sorted(files):
            path = prefix + name
            if path == STATE_NAME:
                continue
            full = os.path.join(root, name)
            records[path] = {"type": "file", "name": path, "size": os.path.getsize(full), "hash": sha256(full)}

    return records


def is_page(name):
    """Pages and the map of pages to scripts go after the scripts they load."""
    return name.endswith(".html") or name == "hashmap.json"


class Server:
    """One FTP connection, opened again when it drops."""

    def __init__(self, host, port, username, password, protocol, folder):
        if protocol not in ("ftp", "ftps"):
            raise SystemExit(f"FTP_PROTOCOL must be ftp or ftps, not {protocol!r}.")

        self.host, self.port = host, int(port)
        self.username, self.password = username, password
        self.tls = protocol == "ftps"
        self.folder = folder
        self.ftp = None
        self.made = set()

    def connect(self):
        ftp = ftplib.FTP_TLS(timeout=TIMEOUT) if self.tls else ftplib.FTP(timeout=TIMEOUT)
        ftp.connect(self.host, self.port)
        ftp.login(self.username, self.password)
        if self.tls:
            ftp.prot_p()
        ftp.set_pasv(True)
        if self.folder not in ("", ".", "./"):
            ftp.cwd(self.folder)
        self.ftp = ftp
        self.made = set()

    def close(self):
        if self.ftp is not None:
            try:
                self.ftp.close()
            except OSError:
                pass
        self.ftp = None

    def run(self, label, action):
        """Run action(ftp), reconnecting and retrying when the connection fails."""
        for attempt in range(1, ATTEMPTS + 1):
            try:
                if self.ftp is None:
                    self.connect()
                return action(self.ftp)
            except ftplib.error_perm:
                # A 5xx reply (no such file, no permission): trying again gives the same answer.
                raise
            except (OSError, EOFError, ftplib.Error) as error:
                self.close()
                if attempt == ATTEMPTS:
                    raise SystemExit(
                        f"Gave up on {label} after {ATTEMPTS} tries: {type(error).__name__}: {error}\n"
                        "The files uploaded so far are saved in the state file: run the job again to continue."
                    )
                wait = min(60, 5 * attempt)
                log(f"  {label}: {type(error).__name__}: {error}; reconnecting in {wait} s ({attempt}/{ATTEMPTS})")
                time.sleep(wait)

    def read_state(self):
        buffer = io.BytesIO()

        def fetch(ftp):
            buffer.seek(0)
            buffer.truncate()
            try:
                ftp.retrbinary(f"RETR {STATE_NAME}", buffer.write)
            except ftplib.error_temp as error:
                # Some servers answer 450 rather than 550 for a missing file.
                if str(error).startswith("450"):
                    raise ftplib.error_perm(str(error)) from error
                raise

        try:
            self.run("reading the state file", fetch)
        except ftplib.error_perm:
            log("No state file on the server: every file will be uploaded.")
            return {}

        try:
            data = json.loads(buffer.getvalue().decode("utf-8"))["data"]
        except (ValueError, KeyError, TypeError):
            log("The state file on the server cannot be read: every file will be uploaded.")
            return {}

        return {record["name"]: record for record in data if isinstance(record, dict) and "name" in record}

    def write_state(self, records):
        body = json.dumps(
            {
                "description": STATE_DESCRIPTION,
                "version": STATE_VERSION,
                "generatedTime": int(time.time() * 1000),
                "data": sorted(records.values(), key=lambda record: (record["type"] != "folder", record["name"])),
            },
            indent=4,
        ).encode("utf-8")
        temporary = STATE_NAME + ".tmp"

        def write(ftp):
            ftp.storbinary(f"STOR {temporary}", io.BytesIO(body))
            try:
                ftp.rename(temporary, STATE_NAME)
            except ftplib.error_perm:
                # Some servers will not rename over an existing file.
                ftp.delete(STATE_NAME)
                ftp.rename(temporary, STATE_NAME)

        self.run("saving the state file", write)

    def upload(self, site, name):
        def store(ftp):
            self.make_folders(ftp, posixpath.dirname(name))
            with open(os.path.join(site, name), "rb") as file:
                ftp.storbinary(f"STOR {name}", file)

        self.run(f"uploading {name}", store)

    def make_folders(self, ftp, folder):
        parts = [part for part in folder.split("/") if part]

        for depth in range(1, len(parts) + 1):
            path = "/".join(parts[:depth])
            if path in self.made:
                continue
            try:
                ftp.mkd(path)
            except ftplib.error_perm:
                pass  # It exists.
            self.made.add(path)

    def delete(self, record):
        def remove(ftp):
            try:
                if record["type"] == "folder":
                    ftp.rmd(record["name"])
                else:
                    ftp.delete(record["name"])
            except ftplib.error_perm:
                pass  # Already gone, or a folder that still holds files we did not upload.

        self.run(f"deleting {record['name']}", remove)


def main():
    args = [arg for arg in sys.argv[1:] if arg != "--dry-run"]
    dry_run = "--dry-run" in sys.argv[1:]

    if len(args) != 1 or not os.path.isdir(args[0]):
        raise SystemExit(__doc__)

    site = args[0]
    env = os.environ
    server = Server(
        env.get("FTP_SERVER", ""),
        env.get("FTP_PORT") or 21,
        env.get("FTP_USERNAME", ""),
        env.get("FTP_PASSWORD", ""),
        env.get("FTP_PROTOCOL") or "ftps",
        env.get("FTP_DIR") or "./",
    )

    local = local_records(site)
    remote = server.read_state()

    files = [record for record in local.values() if record["type"] == "file"]
    changed = [record for record in files if remote.get(record["name"], {}).get("hash") != record["hash"]]
    changed.sort(key=lambda record: (is_page(record["name"]), record["name"]))
    gone = [record for name, record in remote.items() if name not in local]
    # Files before folders, and the deepest folders first.
    gone.sort(key=lambda record: (record["type"] == "folder", -record["name"].count("/"), record["name"]))

    log(f"{len(files)} files in the site: {len(changed)} to upload, {len(gone)} to delete, {len(files) - len(changed)} unchanged.")

    if dry_run:
        for record in changed:
            log(f"upload {record['name']}")
        for record in gone:
            log(f"delete {record['name']}")
        return

    # What the server holds as the deploy goes; saved every SAVE_EVERY changes.
    state = dict(remote)
    done = 0

    def progress():
        nonlocal done
        done += 1
        if done % SAVE_EVERY == 0:
            server.write_state(state)

    for record in changed:
        log(f"upload {record['name']}")
        server.upload(site, record["name"])
        folder = posixpath.dirname(record["name"])
        while folder:
            state[folder] = local.get(folder, {"type": "folder", "name": folder})
            folder = posixpath.dirname(folder)
        state[record["name"]] = record
        progress()

    for record in gone:
        log(f"delete {record['name']}")
        server.delete(record)
        state.pop(record["name"], None)
        progress()

    server.write_state(local)
    server.close()
    log(f"Done: {len(changed)} uploaded, {len(gone)} deleted.")


if __name__ == "__main__":
    main()
