<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Document templates</title>
    <style>
        :root { --primary: #0F766E; --border: #E5E7EB; --muted: #6B7280; --bg: #F9FAFB; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", Tahoma, sans-serif; color: #111827; background: var(--bg); height: 100vh; display: flex; }
        aside { width: 280px; flex-shrink: 0; background: #fff; border-right: 1px solid var(--border); overflow-y: auto; }
        aside h1 { font-size: 15px; margin: 0; padding: 16px; border-bottom: 1px solid var(--border); }
        .template { display: block; width: 100%; text-align: left; padding: 12px 16px; border: 0; border-bottom: 1px solid var(--border); background: none; cursor: pointer; font: inherit; }
        .template:hover { background: var(--bg); }
        .template.active { background: #F0FDFA; box-shadow: inset 3px 0 0 var(--primary); }
        .template strong { display: block; font-size: 14px; }
        .template small { display: block; color: var(--muted); font-size: 12px; margin-top: 2px; }
        .badges { margin-top: 6px; }
        .badge { display: inline-block; font-size: 11px; padding: 1px 6px; border-radius: 4px; background: var(--bg); border: 1px solid var(--border); color: var(--muted); }
        main { flex: 1; display: flex; flex-direction: column; min-width: 0; }
        .toolbar { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; padding: 10px 16px; background: #fff; border-bottom: 1px solid var(--border); }
        .toolbar label { font-size: 13px; color: var(--muted); display: flex; gap: 6px; align-items: center; }
        select { font: inherit; font-size: 13px; padding: 4px 6px; border: 1px solid var(--border); border-radius: 6px; background: #fff; }
        .actions { margin-left: auto; display: flex; gap: 8px; }
        .button { font-size: 13px; padding: 6px 12px; border-radius: 6px; border: 1px solid var(--primary); color: var(--primary); text-decoration: none; background: #fff; }
        .button.primary { background: var(--primary); color: #fff; }
        iframe { flex: 1; width: 100%; border: 0; background: #525659; }
        .empty { padding: 32px; color: var(--muted); }
        @media (max-width: 760px) { body { flex-direction: column; height: auto; } aside { width: 100%; max-height: 40vh; } iframe { height: 80vh; } }
    </style>
</head>
<body>
    <aside>
        <h1>Document templates</h1>
        @foreach ($templates as $name => $template)
            <button type="button" class="template" data-name="{{ $name }}" data-locales="{{ implode(',', $template['locales']) }}" data-word="{{ $template['word'] ? 1 : 0 }}" data-pdf="{{ $template['pdf'] ? 1 : 0 }}">
                <strong>{{ $template['title'] }}</strong>
                <small>{{ $name }}</small>
                @if ($template['description'])<small>{{ $template['description'] }}</small>@endif
                <div class="badges">
                    @if ($template['pdf'])<span class="badge">PDF</span>@endif
                    @if ($template['word'])<span class="badge">Word</span>@endif
                    <span class="badge">{{ $template['source'] }}</span>
                </div>
            </button>
        @endforeach
    </aside>
    <main>
        <div class="toolbar">
            <label>Language <select id="locale"></select></label>
            <label>Digits
                <select id="numerals"><option value="latin">123</option><option value="arabic">١٢٣</option></select>
            </label>
            <label>Engine
                <select id="engine">
                    <option value="">Default</option>
                    @foreach ($engines as $engine)<option value="{{ $engine }}">{{ $engine }}</option>@endforeach
                </select>
            </label>
            <label>View
                <select id="format"><option value="pdf">PDF</option><option value="html">HTML</option></select>
            </label>
            <div class="actions">
                <a id="open-pdf" class="button" target="_blank" rel="noopener">Open PDF</a>
                <a id="download-word" class="button primary">Download Word</a>
            </div>
        </div>
        @if ($templates === [])
            <div class="empty">No templates found.</div>
        @else
            <iframe id="preview" title="Preview"></iframe>
        @endif
    </main>

    <script>
        const base = @json($base);
        let current = @json($selected);
        const $ = (id) => document.getElementById(id);

        function url(format) {
            const params = new URLSearchParams({
                locale: $('locale').value,
                numerals: $('numerals').value,
                format,
            });

            if ($('engine').value) params.set('engine', $('engine').value);

            return `${base}/${encodeURIComponent(current)}?${params}`;
        }

        function select(name) {
            const button = document.querySelector(`.template[data-name="${CSS.escape(name)}"]`);
            if (!button) return;

            current = name;
            document.querySelectorAll('.template').forEach((b) => b.classList.toggle('active', b === button));

            const locale = $('locale').value;
            $('locale').innerHTML = button.dataset.locales.split(',')
                .map((l) => `<option value="${l}">${l}</option>`).join('');
            if (button.dataset.locales.split(',').includes(locale)) $('locale').value = locale;

            $('download-word').style.display = button.dataset.word === '1' ? '' : 'none';
            $('open-pdf').style.display = button.dataset.pdf === '1' ? '' : 'none';
            history.replaceState(null, '', `?template=${encodeURIComponent(name)}`);
            refresh();
        }

        function refresh() {
            $('open-pdf').href = url('pdf');
            $('download-word').href = url('docx');
            if ($('preview')) $('preview').src = url($('format').value);
        }

        document.querySelectorAll('.template').forEach((b) => b.addEventListener('click', () => select(b.dataset.name)));
        ['locale', 'numerals', 'engine', 'format'].forEach((id) => $(id).addEventListener('change', refresh));
        select(current);
    </script>
</body>
</html>
