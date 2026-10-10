<?php

use BiztechEG\EasyPdfWord\View\PageLayout;

/*
| The PDF layout in plain PHP: HTML with <?= ?> tags. It gets $doc and every
| key of the data as a variable ($title ...). Print values through
| $doc->e() so they are escaped. Lay out with tables: mPDF has no flexbox.
*/

$primary = $doc->theme('primary', '#0F766E');

ob_start();
?>
<style>
    h1 { color: <?= $doc->e($primary) ?>; }
</style>
<?php
$styles = ob_get_clean();
ob_start();
?>
<h1><?= $doc->e($title) ?></h1>

<p><?= $doc->e($doc->t('intro')) ?></p>
<?php
echo PageLayout::render($doc, ob_get_clean(), $title, $styles);
