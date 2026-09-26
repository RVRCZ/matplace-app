<?php

return [
    'title' => 'Privacy Policy',
    'effective' => 'Effective 26 September 2026 · matplace s.r.o.',
    'footer' => ['privacy' => 'Privacy Policy', 'terms' => 'Print farm terms', 'youtube' => 'Our YouTube channel'],

    'sections' => [
        [
            'h' => '1. Data controller',
            'p' => ['The controller of your personal data is matplace s.r.o., company ID 22499008, registered office Rybná 716/24, Staré Město, 110 00 Prague 1, Czech Republic, registered in the Commercial Register kept by the Municipal Court in Prague, file C 417491 ("we"). Contact: info@matplace.com.'],
        ],
        [
            'h' => '2. What data we process',
            'li' => [
                'Account data – name, email, phone, address (if you fill them in), linked Google or Facebook account.',
                'Uploaded files and requests – 3D models, photos and text descriptions we price or generate a model from.',
                'Order data – material, colour, price, delivery method, shipping address, print status.',
                'Print recording – pictures from the camera inside the printer and the time-lapse video made from them. They show only the print bed and the printed object.',
                'Payment data – processed solely by the payment gateway Stripe; we store only the payment identifier and status and your credit balance.',
                'Technical data – IP address, browser type, time of access, necessary cookies.',
            ],
        ],
        [
            'h' => '3. Why we process data',
            'li' => [
                'Pricing, printing and handing over orders, managing your account and credit – performance of a contract (Art. 6(1)(b) GDPR).',
                'Accounting and tax duties – legal obligation (Art. 6(1)(c) GDPR).',
                'Security of the service and prevention of abuse – legitimate interest (Art. 6(1)(f) GDPR).',
                'Publishing the time-lapse video on YouTube – only with your consent (Art. 6(1)(a) GDPR), see section 5.',
            ],
        ],
        [
            'h' => '4. Who we share data with',
            'p' => ['We do not sell data. We share it only as far as necessary with these processors:'],
            'li' => [
                'Stripe, Inc. – card payments when you top up credit (stripe.com/privacy).',
                'Anthropic, PBC – recognising the object in a photo and checking uploaded photos (anthropic.com/privacy).',
                'Tripo (VAST) – generating a 3D model from a photo or text, only when you use this feature.',
                'Google LLC (YouTube) – time-lapse videos you agreed to publish.',
                'Hetzner Online GmbH – hosting, servers in the EU.',
                'Carriers – only name, address and phone to deliver a parcel.',
            ],
        ],
        [
            'h' => '5. Print videos and YouTube API Services',
            'p' => [
                'Every print on our farm is filmed by the camera inside the printer, and we make a short time-lapse video from the pictures. You can watch it on your order page.',
                'If you agree (an optional checkbox when ordering, or a button on the order page), our server uploads the video to our own YouTube channel "Matplace – 3D" as a private video. It becomes public only after our team has checked it. For the upload we use YouTube API Services. We send YouTube only the video itself and its title and description (material, colour, printer type, print time); never your name, email or uploaded files. With the order we store only the YouTube video ID and its status.',
                'You can take the consent back at any time on the order page. The video is then deleted from YouTube automatically. You can also ask for deletion at info@matplace.com.',
                'Our application does not access your Google or YouTube account and does not read any of your YouTube data. Access to the YouTube API is used only by our team for our own channel.',
                'By watching videos on YouTube you agree to the YouTube Terms of Service (https://www.youtube.com/t/terms). Google\'s processing of data is governed by the Google Privacy Policy (https://policies.google.com/privacy). You can review and revoke any application\'s access to your Google account at https://myaccount.google.com/permissions.',
            ],
        ],
        [
            'h' => '6. Transfers outside the EU',
            'p' => ['Stripe, Anthropic and Google are based in the USA, Tripo outside the EU. Transfers rely on the European Commission\'s standard contractual clauses or another mechanism under Chapter V GDPR.'],
        ],
        [
            'h' => '7. How long we keep data',
            'li' => [
                'Account – while it exists; deleted or anonymised within 30 days after closing it.',
                'Orders and payments – 5 years after completion (accounting and tax rules), invoices up to 10 years as the law requires.',
                'Photos for recognition and generation – at most 1 day; anonymously uploaded files 30 days.',
                'Time-lapse videos – with the order while your account exists; on YouTube until you take the consent back or we delete them.',
                'Technical logs – at most 12 months.',
            ],
        ],
        [
            'h' => '8. Your rights',
            'p' => ['You have the right to access your data, to rectification, erasure, restriction of processing, data portability, to object, to withdraw consent at any time and to lodge a complaint with the Czech Office for Personal Data Protection (uoou.gov.cz). Send requests to info@matplace.com; we answer within 30 days.'],
        ],
        [
            'h' => '9. Deleting your account and data',
            'p' => ['Write from your account\'s email to info@matplace.com with the subject "Delete account". Within 30 days we delete or anonymise your profile, contact data, uploaded files and Google/Facebook links, and delete your videos from YouTube. Documents we must keep by law stay in our accounts, anonymised from your profile.'],
        ],
        [
            'h' => '10. Cookies and security',
            'p' => ['We use only necessary cookies for signing in, protecting forms and remembering your choices. We use no third-party advertising or tracking cookies. Traffic is encrypted (TLS), passwords are stored as one-way hashes, and we never see card numbers.'],
        ],
        [
            'h' => '11. Changes',
            'p' => ['We will announce substantial changes on the website or by email at least 14 days in advance.'],
        ],
    ],
];
