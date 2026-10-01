<?php

/*
 * Tool pages as content: one file per tool in tools_seo/ (title, description, h1, intro, steps, faq, examples),
 * so the three languages of one tool can be compared side by side. Read through App\Support\ToolSeo.
 */
$tools = [];
foreach (glob(__DIR__.'/tools_seo/*.php') ?: [] as $file) {
    $tools[basename($file, '.php')] = require $file;
}

return $tools;
