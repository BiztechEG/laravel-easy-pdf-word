<?php

use BiztechEG\EasyPdfWord\Builder\DocumentBuilder;
use BiztechEG\EasyPdfWord\Support\DocContext;

/*
| The Word (.docx) layout. Delete this file if you only need PDFs, or
| replace it with a word.docx designed in Word with ${placeholders}.
|
| Blocks: heading, paragraph, table, image, qr, spacer, pageBreak, line.
| If you delete pdf.blade.php, this layout makes the PDF too; rename it to
| layout.php to make that clear.
*/

return function (DocumentBuilder $word, array $data, DocContext $doc): void {
    $word->heading($data['title']);
    $word->paragraph($doc->t('intro'));
};
