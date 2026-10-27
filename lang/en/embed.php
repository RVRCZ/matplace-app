<?php

// The calculator for other sites (/embed: the page with the code; /embed/calc: the small page inside the frame), docs/AN.md §12.
return [
    'title' => 'A 3D print price calculator for your site',
    'description' => 'Put a 3D printing calculator on your site or shop: a visitor uploads a model and sees the price and print time at once. One line of code, free.',
    'h1' => 'A 3D print price calculator for your site',
    'lead' => 'Do you write about 3D printing or sell filament? Embed our calculator: a reader uploads a model (STL, 3MF, STEP), sees the estimated price and print time at once and goes to matplace.com in one tap to order the print or download the model for their own printer. Free, no account, no cookies.',
    'code_title' => 'The code to paste',
    'code_hint' => 'Copy the line into an article or a page template. The calculator speaks the language of the page you copy the code from; for a Czech site use the Czech version of this page.',
    'copy' => 'Copy the code',
    'copied' => 'Copied',
    'preview_title' => 'How it looks',
    'rules_title' => 'Good to know',
    'rules' => [
        'The calculator prices printing with us (the matplace farm); delivery is added when ordering.',
        'Files :formats up to 120 MB; a large model (millions of triangles) gets an estimate only, the exact price comes when ordering.',
        'The uploaded file and the result belong to whoever holds the link; nothing is stored in your site\'s cookies.',
        'We see visits from your page as the source "embedded calculator" with the name of your site; we collect nothing more about your readers.',
        'You can remove the code any time; the results your readers opened on matplace.com stay available.',
    ],
    'contact' => 'Want the calculator in another size or with a preset material? Write to us:',

    // the small page inside the frame
    'frame_title' => '3D print price calculator · matplace',
    'heading' => '3D print price',
    'pick' => 'Pick or drop a model',
    'formats' => ':formats, up to :max MB',
    'reading' => 'Uploading the file…',
    'measuring' => 'Measuring the model…',
    'counting' => 'Working out the exact price…',
    'big_model' => 'A large model: the exact price comes when ordering.',
    'estimate' => 'Print price with us, to orient by',
    'exact' => 'Exact print price with us',
    'time' => 'Print time :time',
    'price_note' => 'Without delivery; the colour, material and number of pieces are set on matplace.com.',
    'open' => 'Open on matplace.com',
    'again' => 'Another file',
    'failed' => 'That did not work. Try again, or open matplace.com.',
    'too_big' => 'The file is larger than :max MB.',
    'not_model' => 'We cannot read this file. Upload an STL, 3MF or STEP.',
    'footer_link' => 'A calculator for your site',
    'footer' => '3D printing to order:',
];
