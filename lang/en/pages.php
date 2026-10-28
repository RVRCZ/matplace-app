<?php

// Static pages: about, contact, FAQ, complaints, terms of use, business terms, cookies.
// Translation of lang/cs/pages.php (the legally binding version); the structure must stay identical.
return [
    'updated' => '1 October 2026',

    'about' => [
        'title' => 'About us: 3D printing on our own print farm',
        'description' => 'matplace is a Czech 3D printing service. Upload a model, see the price at once and we print it on our own print farm in Czechia. We ship to EU countries.',
        'lead' => 'matplace is an online 3D printing service. We print on our own printers in Czechia, you know the price before you order, and we send the finished print by Packeta to a pickup point or to your door.',
        'sections' => [
            [
                'h' => 'What we do',
                'p' => [
                    'matplace grew out of a simple idea: 3D printing should be open to anyone with an idea, not only to people who have a printer at home.',
                    'You upload your own model, pick a designer\'s model or make a product in one of our tools. We check the model, orient it for printing and work out the exact price. You choose the colour and the delivery method, pay at the end of the order by card or from credit, and the printer starts printing.',
                ],
            ],
            [
                'h' => 'How it works',
                'li' => [
                    'Upload a file, pick a model from the catalogue or make one in a tool; you pay nothing yet.',
                    'You see the preview, the print time, the material used and the price including VAT at once, with no sign-up and no payment.',
                    'In the order you choose the colour, quality and delivery, and only at its end you pay by card or from credit.',
                    'Follow the print on the order page. We send the finished print by Packeta to a pickup point or to your door.',
                ],
            ],
            [
                'h' => 'We print it ourselves',
                'p' => [
                    'Every order is printed on our own print farm in Czechia. We do not pass orders on to anyone else, so the result is our responsibility.',
                    'When you order, the amount paid is only held; it is charged once the print is finished. If the print does not start or fails through our fault, all of it comes back.',
                ],
            ],
            [
                'h' => 'For designers',
                'p' => [
                    'Do you design models? Publish them in your portfolio on matplace. Customers have them printed by us and you get a reward for every printed piece. You do not need a printer.',
                    'You set the reward for each model yourself. You can also allow the file to be downloaded free of charge under a licence of your choice.',
                ],
            ],
            [
                'h' => 'Do you have your own printer?',
                'p' => [
                    'The tools on the site are free. You can download a model as an STL file or as a ready 3MF project for your printer and print it yourself.',
                ],
            ],
            [
                'h' => 'Operator',
                'p' => [
                    'The service is operated by matplace s.r.o., company ID 22499008, registered office Rybná 716/24, Staré Město, 110 00 Prague 1, Czech Republic, registered in the Commercial Register kept by the Municipal Court in Prague, file C 417491. Contact: info@matplace.com.',
                ],
            ],
        ],
    ],

    'contact' => [
        'title' => 'Contact and invoicing details',
        'description' => 'How to reach matplace s.r.o.: the e-mail for questions about orders and for complaints, the registered office, company ID and Commercial Register entry.',
        'lead' => 'Write to us by e-mail. If your question is about a particular print, please include the order number.',
        'sections' => [
            [
                'h' => 'Write to us',
                'p' => [
                    'E-mail: info@matplace.com',
                    'Use this address for questions about orders, complaints, requests about your personal data and offers of cooperation.',
                ],
            ],
            [
                'h' => 'Operator and invoicing details',
                'li' => [
                    'matplace s.r.o.',
                    'Registered office: Rybná 716/24, Staré Město, 110 00 Prague 1, Czech Republic',
                    'Company ID (IČO): 22499008',
                    'Commercial Register entry: Municipal Court in Prague, file C 417491',
                ],
            ],
            [
                'h' => 'Complaints and personal data',
                'p' => [
                    'Complaints are made by e-mail to info@matplace.com. The Complaints page describes the procedure.',
                    'The Privacy Policy describes how we handle personal data. Send requests about your data to the same address.',
                ],
            ],
        ],
    ],

    'faq' => [
        'title' => 'Frequently asked questions about 3D printing',
        'description' => 'Answers to common questions: the price of a 3D print, materials, file formats, print time, delivery to EU countries, credit and payment, complaints.',
        'lead' => 'Short answers to what people ask most. Is your question missing? Write to info@matplace.com.',
        'items' => [
            [
                'q' => 'How much does a print cost?',
                'a' => 'We work out the exact price for your model before you order. It is made up of printer time, the material used and an order handling fee; small orders are subject to a minimum price. The price includes VAT, and delivery is shown separately before you pay.',
            ],
            [
                'q' => 'What materials do you print with?',
                'a' => 'The choice depends on what is loaded in the printers at the moment: most often PLA and PETG in various colours and finishes. You see the current materials and colours when you order.',
            ],
            [
                'q' => 'Which files do you accept, and what if I have none?',
                'a' => 'The calculator on the site accepts STL, 3MF, OBJ and STEP files. A print order itself takes an STL upload. No file? Pick a model in the catalogue, make a product in one of the tools or have a model generated from a photo or a description.',
            ],
            [
                'q' => 'How long does it take?',
                'a' => 'We work out the print time for every model and you see it before you order. If a printer is free, printing starts right after payment. Otherwise the order joins the queue and its page shows the estimated start and finish. We e-mail you when the status changes. Add the carrier\'s transit time to the print time.',
            ],
            [
                'q' => 'How do I get my print, and which countries do you ship to?',
                'a' => 'We ship by Packeta to a pickup point or to your door, to countries of the European Union only. In Czechia a pickup point costs 99 Kč and home delivery 149 Kč; the countries we ship to and the delivery price are shown in the order before you pay. A print longer than 70 cm cannot be shipped, and we do not take such an order. We do not offer pickup in person for now.',
            ],
            [
                'q' => 'How do I pay, and what is credit?',
                'a' => 'You know the price before you pay. You pay by card (Stripe) or from the credit on your account. By card you pay exactly the price of the order rounded up to whole crowns or euros; the few pennies over stay as credit for the next print. You can also top up credit in advance (100 to 20,000 Kč, €5 to €800). The amount is held when you pay and charged only once the print is finished; if the print does not start or fails through our fault, all of it comes back. We do not offer cash on delivery, and unused credit is not paid out in cash.',
            ],
            [
                'q' => 'Which currency do I pay in?',
                'a' => 'Customers in Czechia pay in Czech crowns, everyone else in euros. Before your first payment you can switch the currency in the site header. The first payment fixes the currency of your account, and all later payments and prices are in that currency only.',
            ],
            [
                'q' => 'What if the print fails?',
                'a' => 'If the print does not start or fails through our fault, the held amount comes back in full and we tell you by e-mail. You can then order the print again.',
            ],
            [
                'q' => 'Can I cancel an order?',
                'a' => 'Yes, on the order page, until the print is finished. Before printing starts, the whole amount comes back. If the print is already running, we charge the handling fee and the part already printed and return the rest; you see the amount before you confirm the cancellation.',
            ],
            [
                'q' => 'Can I download a model and print it on my own printer?',
                'a' => 'Yes, free of charge. A model you upload or make in the tools can be downloaded as an STL file or as a 3MF project prepared for your printer. Designers\' models can be downloaded only when the author has allowed it.',
            ],
            [
                'q' => 'How do designers\' models work?',
                'a' => 'The catalogue holds models that designers offer for printing. We print them on our farm and the author gets a reward for every piece, shown separately in the price. For some models the author has also allowed a free download of the file under a licence of their choice.',
            ],
            [
                'q' => 'How do I complain about a defective print?',
                'a' => 'Write to info@matplace.com, give the order number, describe the defect and attach photos. We settle a complaint within 30 days at the latest. Small support marks, visible layers and deviations of tenths of a millimetre belong to 3D printing and are not defects.',
            ],
            [
                'q' => 'Can I return a print within 14 days?',
                'a' => 'No. Every print is made to order from your file or your settings, and for such goods the law gives no right to withdraw from the contract within 14 days. Your right to complain about defects remains.',
            ],
            [
                'q' => 'How do I delete my account?',
                'a' => 'In your account profile under "Delete account", or by e-mail to info@matplace.com. We delete your personal data; orders and payments have to be kept for accounting, but they are no longer linked to you. Prints already paid for are finished. Unused credit is forfeited when the account is deleted.',
            ],
        ],
    ],

    'complaints' => [
        'title' => 'Complaints and returns',
        'description' => 'How to complain about a matplace print: what counts as a defect in 3D printing, what to send us, how fast we settle it and why there is no 14-day return.',
        'lead' => 'Not happy with your print? Here is how to complain about a defect and what you can expect from us.',
        'sections' => [
            [
                'h' => '1. What we are liable for',
                'p' => [
                    'We are liable for the print being free of defects when you receive it: it matches the model you ordered and the material, colour and settings you chose.',
                    'If you are a consumer, you may complain about a defect that shows on the print within two years of receipt.',
                ],
            ],
            [
                'h' => '2. What is not a defect',
                'p' => [
                    'We do not treat the following as defects:',
                ],
                'li' => [
                    'small support marks, visible layers and deviations of tenths of a millimetre, which belong to 3D printing,',
                    'properties that come from the model itself: we print it as it is and do not guarantee that the part will serve the purpose you designed or chose it for,',
                    'wear caused by ordinary use and damage you caused yourself.',
                ],
            ],
            [
                'h' => '3. How to make a complaint',
                'p' => [
                    'Send the complaint by e-mail to info@matplace.com without undue delay after you find the defect. Please include:',
                ],
                'li' => [
                    'the order number,',
                    'a description of the defect,',
                    'photos that show the defect,',
                    'the remedy you propose.',
                ],
            ],
            [
                'h' => '4. How we settle a complaint',
                'p' => [
                    'We confirm receipt of the complaint by e-mail. We settle it without undue delay, within 30 days of receiving it at the latest, unless we agree on a longer period with you.',
                    'If the complaint is justified, we print the item again or repair the defect. If that is impossible or would be disproportionate, you may ask for a reasonable discount or withdraw from the contract. The discount or the refunded price is added to your credit.',
                    'On request, send or hand the defective print back to us; we give you the address while handling the complaint. If the complaint is justified, we reimburse the reasonable cost of sending it.',
                ],
            ],
            [
                'h' => '5. Parcel damaged in transit',
                'p' => [
                    'Check the parcel when you receive it. If it is damaged, take photos of the packaging and the contents and write to us at info@matplace.com with the order number. We handle damage in transit like any other defect.',
                ],
            ],
            [
                'h' => '6. Cancelling an order and returning a print',
                'p' => [
                    'You can cancel an order on its page until the print is finished. Before printing starts, all the credit comes back. If the print is already running, we charge the handling fee and the part already printed and return the rest.',
                    'Every print is made from your file or your settings. For goods made to the consumer\'s specifications the law gives no right to withdraw from the contract within 14 days without giving a reason (Section 1837(d) of the Czech Civil Code). We therefore do not take back a finished print that has no defects.',
                ],
            ],
            [
                'h' => '7. Out-of-court dispute resolution',
                'p' => [
                    'If we cannot reach an agreement, a consumer may turn to the Czech Trade Inspection Authority (www.coi.cz, adr.coi.cz), which resolves consumer disputes out of court.',
                ],
            ],
            [
                'h' => '8. Contact for complaints',
                'p' => [
                    'matplace s.r.o., company ID 22499008, registered office Rybná 716/24, Staré Město, 110 00 Prague 1, Czech Republic. E-mail: info@matplace.com.',
                ],
            ],
        ],
    ],

    'terms' => [
        'title' => 'Terms of use',
        'description' => 'Terms of use of matplace.com: your account, uploaded files, tools and the calculator, designers\' models and licences, prohibited content and reporting.',
        'lead' => 'The rules for using the website, your account and the tools. Print orders are also governed by the Business Terms.',
        'sections' => [
            [
                'h' => '1. Who we are',
                'p' => [
                    'The website matplace.com is operated by matplace s.r.o., company ID 22499008, registered office Rybná 716/24, Staré Město, 110 00 Prague 1, Czech Republic, registered in the Commercial Register kept by the Municipal Court in Prague, file C 417491. Contact: info@matplace.com.',
                    'These terms apply to the use of the website, your account and the tools. Print orders are also governed by the Business Terms.',
                ],
            ],
            [
                'h' => '2. What the website offers',
                'p' => [
                    'The website offers the services listed below. The tools, the calculator and downloads of your own models are free. Generating a model from a photo or a text has a daily limit; further generations are paid from credit.',
                ],
                'li' => [
                    'a calculator and a check for 3D models, and tools for making your own product,',
                    'downloading a model for your own printer,',
                    'ordering a print on the matplace print farm,',
                    'a catalogue of designers\' models and an inspiration catalogue with links to models on other websites,',
                    'a designer profile with a public portfolio.',
                ],
            ],
            [
                'h' => '3. Your account',
                'p' => [
                    'You create an account with an e-mail and a password, or through Google or Facebook. The e-mail has to be verified; without that you cannot order a print, top up credit or turn on a designer profile.',
                    'Give true information and keep your sign-in details safe. You are responsible for what is done from your account.',
                    'You can delete your account in your profile at any time. Deleting it erases your personal data, unused credit is forfeited and prints already paid for are finished. We may suspend an account that breaks these terms.',
                ],
            ],
            [
                'h' => '4. Files you upload',
                'p' => [
                    'You are responsible for the files you upload. Upload only models and photos that you made or are allowed to use.',
                    'Uploaded files are stored privately and used only for the purpose you uploaded them for: working out the price, working in a tool and fulfilling your order. Files uploaded without signing in are deleted after 30 days.',
                ],
            ],
            [
                'h' => '5. What is prohibited',
                'li' => [
                    'uploading or ordering weapons, their parts and parts meant to circumvent the law,',
                    'printing other people\'s models for sale without the author\'s consent, or infringing somebody else\'s copyright or industrial rights in any other way,',
                    'passing off other people\'s models as your own,',
                    'collecting the content of the website automatically without our consent,',
                    'disrupting the website or getting round its limits and security.',
                ],
            ],
            [
                'h' => '6. Designers\' models and licences',
                'p' => [
                    'A designer publishes models in their portfolio. By uploading a file the designer confirms that they are the author of the model and grants us the right to print it for customers. For a remix the designer confirms that the licence of the original model allows commercial use and derivative works.',
                    'We never hand out the file of a model whose author has not allowed downloads: it can only be printed on our farm. If the author has allowed downloads, the file comes under the licence the author chose (CC BY, CC BY-SA, CC BY-NC or CC0), and you must comply with it.',
                    'For every printed piece the designer gets the reward they set, but no more than 30% of the print price. The reward is added to the credit on the designer\'s account once the print is finished; printing your own model earns no reward. If we refund the price of a print to the customer, the reward is taken back.',
                ],
            ],
            [
                'h' => '7. Inspiration catalogue',
                'p' => [
                    'The inspiration catalogue shows models published on other websites, such as Printables or MakerWorld. For each one we give the author, a link to the source and the licence as the source states it; we do not guarantee that it is complete or up to date.',
                    'Printing for your own use on the rented printer is possible with every model; prints for sale only where the licence allows it. You download the file at the source and upload it to us; we note the author and the source on the order for you.',
                ],
            ],
            [
                'h' => '8. Reporting unlawful content',
                'p' => [
                    'Do you think a model or other content infringes your rights or the law? Write to info@matplace.com. Give the address of the page, say which work it concerns and how you can show that you are its author or rights holder. We look into the report without undue delay and remove content that was rightly reported.',
                ],
            ],
            [
                'h' => '9. Our liability',
                'p' => [
                    'The results of the tools, the calculator and the automatic checks are an aid. They do not guarantee that a product will serve the purpose you intend; you design and use load-bearing and safety parts at your own risk.',
                    'Prices in the calculator and on model pages are indicative. The binding price is the one you see in the order before you pay.',
                    'We do not guarantee that the website is available without interruption. The content of the website (texts, graphics, code, tools) is protected by copyright and may not be reused without our consent.',
                ],
            ],
            [
                'h' => '10. Personal data and cookies',
                'p' => [
                    'The Privacy Policy describes how personal data is processed; the Cookies page describes the use of cookies.',
                ],
            ],
            [
                'h' => '11. Changes and governing law',
                'p' => [
                    'We may change these terms. We announce a substantial change on the website or by e-mail at least 14 days in advance.',
                    'These terms are governed by Czech law. This does not deprive a consumer of the protection, or of the right to go to court, given by the law of the country where they live. A consumer may also resolve a dispute out of court through the Czech Trade Inspection Authority (www.coi.cz). The Czech version of these terms is the legally binding one.',
                ],
            ],
        ],
    ],

    'business_terms' => [
        'title' => 'Business terms',
        'description' => 'Business terms for 3D printing at matplace: ordering, price, credit and card payment, currency, Packeta delivery in the EU, cancellation and complaints.',
        'lead' => 'The terms on which we print for you. They apply to every print order placed on matplace.com.',
        'sections' => [
            [
                'h' => '1. Seller',
                'p' => [
                    'The seller is matplace s.r.o., company ID 22499008, registered office Rybná 716/24, Staré Město, 110 00 Prague 1, Czech Republic, registered in the Commercial Register kept by the Municipal Court in Prague, file C 417491. Contact: info@matplace.com.',
                    'These terms apply to 3D print orders placed on the website matplace.com. We print only on our own print farm in Czechia.',
                ],
            ],
            [
                'h' => '2. Ordering and conclusion of the contract',
                'p' => [
                    'A signed-in customer with a verified e-mail can order. An order goes like this:',
                ],
                'li' => [
                    'You upload your own model, pick a designer\'s model from the catalogue or use a model from a tool.',
                    'We check the model, orient it for printing and work out the print time, the material used and the price.',
                    'You choose the colour, quality, strength, number of pieces and delivery method. Before you pay, you see the total price including VAT and delivery.',
                    'You confirm that you agree to the terms and pay for the order from your credit. This concludes the contract.',
                ],
            ],
            [
                'h' => '3. Renting the printer: what cannot be printed on it',
                'p' => [
                    'We rent the printer to you: it prints your file with your settings, and it is you who makes the copy. You are responsible for being allowed to print the model (it is yours, its licence allows it, or you print it for your own use). We do not judge any of that.',
                    'The model is printed as it is. Automatic checking, repair and orientation help, but do not guarantee that the part will serve its purpose. Load-bearing and safety parts are printed at your own risk.',
                    'We do not rent the printer for weapons or their parts, for parts meant to circumvent the law, or for prints of other people\'s models meant for sale without the author\'s consent. Such an order is cancelled and the credit returned. When a rights holder rightly asks us to, we stop the print and remove the model.',
                ],
            ],
            [
                'h' => '4. Price',
                'p' => [
                    'You know the price before you order. It is made up of printer time, the material used and an order handling fee; small orders are subject to a minimum price. Prices include VAT.',
                    'For a designer\'s model the price includes the author\'s reward, which is shown separately.',
                    'Prices in the calculator and on model pages are indicative. The binding price is the one you see in the order before you pay.',
                ],
            ],
            [
                'h' => '5. Credit and payment',
                'p' => [
                    'Orders are paid from prepaid credit. You top up credit by payment card through the Stripe gateway; the card details are processed by Stripe and we never see them.',
                    'When you pay for an order, the amount is held on your credit. It is charged only once the print is finished. If the print does not start or fails through our fault, all of it comes back.',
                    'Credit is for paying for matplace services. Unused credit is not paid out in cash and is forfeited when the account is deleted.',
                    'We do not offer cash on delivery. On request we send you a receipt for a credit top-up or for an order by e-mail.',
                ],
            ],
            [
                'h' => '6. Currency',
                'p' => [
                    'Accounts of customers in Czechia are kept in Czech crowns, all others in euros. Before your first payment you can switch the currency on the website. The first payment fixes the currency of the account; after that you cannot change it yourself.',
                    'Prices are calculated in crowns and converted to euros at a fixed rate, rounded up to €0.10.',
                ],
            ],
            [
                'h' => '7. Delivery',
                'p' => [
                    'We send the finished print by Packeta to a pickup point or to your door. We ship only to the countries of the European Union offered in the order. We do not offer pickup in person.',
                    'The print time and the estimated finish are shown with the order. When we ship, we e-mail you a link for tracking the parcel.',
                    'Delivery prices including VAT for a parcel up to 2 kg, to a pickup point and to the door respectively. An account in crowns pays the price in crowns, an account in euros the price in euros:',
                ],
                'li' => [
                    'Czechia: 99 Kč and 149 Kč, or €4.00 and €6.00.',
                    'Slovakia: 149 Kč and 175 Kč, or €5.90 and €6.90.',
                    'Poland, Hungary, Romania, Slovenia, Croatia, Bulgaria, Greece, Lithuania: 199 Kč and 225 Kč, or €7.90 and €8.90.',
                    'Germany, Austria, Spain, Portugal, France, Italy, Latvia: 249 Kč and 299 Kč, or €9.90 and €11.90.',
                    'Netherlands, Belgium, Luxembourg, Ireland, Denmark, Sweden, Finland, Estonia, Cyprus: 399 Kč and 575 Kč, or €15.90 and €22.90.',
                    'A parcel of 2 to 5 kg: a surcharge of 50 Kč or €2. A parcel of 5 to 15 kg, within Czechia only: a surcharge of 100 Kč or €4.',
                    'To Austria, Luxembourg and Ireland we deliver to the door only, to Cyprus to a pickup point only.',
                    'A print longer than 70 cm, or with sides adding up to more than 120 cm, cannot be shipped, and we do not take such an order.',
                ],
            ],
            [
                'h' => '8. Cancelling an order',
                'p' => [
                    'You can cancel an order on its page until the print is finished. Before printing starts, we return all the credit. If the print is already running, we charge the handling fee and the part already printed and return the rest; you see the amount before you confirm.',
                    'We may cancel an order that cannot be printed or that breaches section 3. In that case all the credit is returned.',
                ],
            ],
            [
                'h' => '9. Withdrawal from the contract',
                'p' => [
                    'Every print is made from your file or your settings. These are goods made to the consumer\'s specifications, so you cannot withdraw from the contract within 14 days without giving a reason (Section 1837(d) of the Czech Civil Code).',
                    'This does not affect your rights arising from defective performance.',
                ],
            ],
            [
                'h' => '10. Quality and complaints',
                'p' => [
                    'We are liable for the print being free of defects when you receive it. A consumer may complain about a defect that shows within two years of receipt.',
                    'Small support marks, visible layers and deviations of tenths of a millimetre belong to 3D printing and are not defects.',
                    'Complaints are made by e-mail to info@matplace.com. We settle them within 30 days at the latest. The Complaints page describes the procedure in detail.',
                ],
            ],
            [
                'h' => '11. Recording of the print',
                'p' => [
                    'Every print is filmed by the camera in the printer. The video shows only the print bed and the printed object, and you can watch it on the order page.',
                    'After checking it, we may publish the time-lapse video on the Matplace YouTube channel unless you untick the publishing consent when ordering. You can also refuse publishing later on the order page; the video is then deleted from YouTube. For prints made from photos we publish the video only if you tick the consent yourself.',
                ],
            ],
            [
                'h' => '12. Personal data',
                'p' => [
                    'We process personal data in line with the Privacy Policy. The carrier receives only the name, address and phone number needed for delivery.',
                ],
            ],
            [
                'h' => '13. Disputes and final provisions',
                'p' => [
                    'The contract is governed by Czech law, in particular the Civil Code (Act No. 89/2012 Coll.) and the Consumer Protection Act (Act No. 634/1992 Coll.). This does not deprive a consumer of the protection given by the law of the country where they live.',
                    'A consumer may resolve a dispute out of court through the Czech Trade Inspection Authority (www.coi.cz, adr.coi.cz).',
                    'An order is governed by the terms in force at the moment it is paid. We announce a substantial change of the terms on the website or by e-mail at least 14 days in advance. The Czech version of these terms is the legally binding one.',
                ],
            ],
        ],
    ],

    // 3D printing for businesses: the business intent of the tools as a page of its own (docs/AN.md §8); the delivery prices are
    // filled from config/farm.php (:cz_point, :cz_home, :eu_point), the tools and the prices from their configuration
    'business' => [
        'title' => '3D printing for businesses: QR signs, logos, tags',
        'h1' => '3D printing for businesses',
        'description' => 'Small runs for businesses on our own print farm: QR signs, logos, name plates, coasters and key tags. Price at once, VAT document, EU delivery.',
        'lead' => 'A logo, a text or a link becomes a model in a minute, you see the price at once and order the print without waiting for a quote. We print on our own farm in Czechia and the result is our responsibility.',
        'sections' => [
            ['h' => 'What we print for businesses', 'p' => ['Open a tool, put in the logo, the text or the link, and the model is ready. Nothing is downloaded and nothing is paid until you order.'], 'tools' => ['qr', 'logo', 'badge', 'sign', 'nameplate', 'stencil', 'stamp', 'coaster', 'keychain', 'lightbox', 'text']],
            ['h' => 'What it costs', 'p' => ['The price comes from the material and the print time and you see it before you order, VAT included. Examples from our real calculations:'], 'prices' => true, 'li' => ['You set the number of pieces in the calculator and see the price of the whole run at once; we print several pieces on one plate.', 'The price is for the print; delivery is added by the delivery you choose when ordering.']],
            ['h' => 'How to order', 'li' => ['Make the model in a tool or upload your own file (STL, 3MF, STEP); you pay nothing yet.', 'You see the price including VAT and the print time at once, with no sign-up.', 'In the order you choose the colour, quality, number of pieces and delivery, and only at its end you pay by card or from credit.', 'You follow the print on the order page; we dispatch on the day the print finishes, on working days.']],
            ['h' => 'Delivery and the document', 'p' => ['We send by Packeta to a pickup point (in Czechia :cz_point) or to an address (:cz_home), to EU countries from :eu_point. We send the VAT document of the order by e-mail on request.']],
            ['h' => 'Who prints', 'p' => ['matplace s.r.o., company ID 22499008, Rybná 716/24, 110 00 Prague 1. Orders are printed on our own print farm in Czechia; we pass them on to nobody. Questions: info@matplace.com.']],
        ],
        'items' => [
            ['q' => 'Do we get a VAT document?', 'a' => 'Yes. The price in the calculator includes VAT and we send the document of the order by e-mail on request.'],
            ['q' => 'Can you do larger runs?', 'a' => 'You set the number of pieces in the calculator; larger runs are printed on several plates one after another and you see the price before you order. For hundreds of pieces write to info@matplace.com.'],
            ['q' => 'Can we send our own logo or a finished model?', 'a' => 'Yes. Put the logo as an SVG or a picture into the Logo tool; upload a finished model (STL, 3MF, STEP) to the calculator and see the price at once.'],
            ['q' => 'How long does it take?', 'a' => 'You see the print time of every model in advance. We dispatch the finished print on the day it finishes, on working days; what finishes at the weekend leaves on Monday.'],
        ],
    ],
    'cookies' => [
        'title' => 'Cookies',
        'description' => 'Cookies on matplace: necessary ones for sign-in, language and currency, our own cookie-free statistics, and Google Analytics and Meta only with consent.',
        'lead' => 'Without your consent we use only the cookies the website needs to work. Analytics and marketing tools are loaded only after you allow them.',
        'sections' => [
            [
                'h' => '1. What cookies are',
                'p' => [
                    'Cookies are small text files that a website stores in your browser. Some of them are needed for the website to work. The others we use only with your consent.',
                ],
            ],
            [
                'h' => '2. Necessary cookies',
                'p' => [
                    'These cookies are always stored, because the website does not work without them. They need no consent.',
                ],
                'li' => [
                    'Session: keeps you signed in during your visit and protects forms against misuse.',
                    'Sign-in: remembers you if you choose to stay signed in.',
                    '"lang_seen": remembers that you have visited the website before, so that the home page does not redirect you to another language again. Valid for 1 year.',
                    '"currency": the currency you want to see prices in. Valid for 1 year.',
                    '"ref": the designer\'s link you arrived through, so that the visit is counted for the designer. Valid for 30 days.',
                    'Anonymous session: ties uploaded files and calculations to your browser even when you are not signed in.',
                    '"consent": your choice in the cookie bar.',
                ],
            ],
            [
                'h' => '3. Our own statistics without cookies',
                'p' => [
                    'We measure traffic with our own statistics, directly on our server. They store no extra cookies in your browser. We count page views, where a visit came from and events such as a model download.',
                ],
            ],
            [
                'h' => '4. Analytics and marketing tools',
                'p' => [
                    'These tools are loaded only after you allow them in the cookie bar. Without consent they are not loaded.',
                ],
                'li' => [
                    'Analytics: Google Analytics 4. It helps us understand how the website is used.',
                    'Marketing: the Meta pixel. It measures how well our advertising on Meta\'s networks works.',
                ],
            ],
            [
                'h' => '5. Giving, changing and withdrawing consent',
                'p' => [
                    'On your first visit a bar appears in which you allow analytics and marketing separately, or refuse both. Your choice is stored in the "consent" cookie.',
                    'You can change or withdraw your consent at any time, as easily as you gave it: the "Cookie settings" link in the footer of every page (and the button under this text) opens the bar again. You can also block or delete cookies in your browser settings; without the necessary cookies, however, signing in and ordering will not work.',
                ],
            ],
            [
                'h' => '6. Third parties',
                'p' => [
                    'If you give consent, the following companies also process data about your visit, under their own policies:',
                ],
                'li' => [
                    'Google: policies.google.com/privacy',
                    'Meta: facebook.com/privacy/policy',
                ],
            ],
            [
                'h' => '7. Contact',
                'p' => [
                    'Send questions about cookies and privacy to info@matplace.com. The website is operated by matplace s.r.o., company ID 22499008.',
                ],
            ],
        ],
    ],

    // the local entry page "3D printing Prague" (docs/AP.md §8): only what is true today (the farm prints in Prague, Packeta, no pickup yet)
    'praha' => [
        'title' => '3D printing in Prague: made to order from our own farm',
        'h1' => '3D printing in Prague, made to order',
        'description' => 'Made-to-order 3D printing from our farm in Prague: upload a model and see the price at once, or make one free in your browser. Packeta delivery in the EU.',
        'lead' => 'We print on our own farm in Prague and we answer for the result. Upload an STL, 3MF, OBJ or STEP and see the price and the print time at once, no sign-up. If you have no model, make one free in your browser: from a photo, a name, a picture or the size of a drawer.',
        'sections' => [
            ['h' => 'What we print in Prague', 'p' => ['Orders are printed by our own print farm in Prague from PLA, PETG, ASA and TPU, in 0.2 mm layers as standard on a 250 × 250 mm bed; a bigger model is split into parts with pins. Most often we print busts and figurines from a photo, lithophanes, QR signs, drawer organisers, 3D city maps and gifts with a name. Open any tool, the model is ready in a minute and nothing is paid until you order.'], 'tools' => ['figure', 'relief', 'qr', 'organizer', 'map', 'gingerbread', 'nameplate', 'cutter']],
            ['h' => 'What 3D printing costs', 'p' => ['The price comes from the material and the print time and you see it before ordering, VAT included; the minimum order is 99 CZK. Examples from our real calculations:'], 'prices' => true, 'li' => ['Enter the number of pieces in the calculation and see the price of the whole batch at once.', 'For big models the calculation first shows a range and, after the slicer has run, the exact price; orders are always placed from the exact one.']],
            ['h' => 'Delivery in Prague and Czechia', 'p' => ['We dispatch the finished print on the day it comes off the printer, on working days, by Packeta to a pickup point or a parcel box (:cz_point) or to your door (:cz_home). Prague has hundreds of pickup points and boxes and a parcel usually arrives there the next working day. Pickup at the farm is not offered yet.'], 'li' => ['To Slovakia from :sk_point, to other EU countries from :eu_point.', 'As soon as we hand the parcel over, an e-mail with the tracking link arrives.']],
            ['h' => 'How to order', 'li' => ['Upload a model to the calculator at https://matplace.com, or make one in a tool at https://matplace.com/tools; nothing is paid yet.', 'You see the price with VAT, the print time and a 3D preview at once, without signing up.', 'In the order you choose the material, a colour from the spools we have in stock, the number of pieces and the delivery; you pay by card only at the end.']],
            ['h' => 'Who prints', 'p' => ['matplace s.r.o., company ID 22499008, Rybná 716/24, 110 00 Prague 1. Orders are printed by our own print farm in Prague; we pass them on to nobody. For businesses we print small batches with a VAT document, see https://matplace.com/business. Questions: info@matplace.com.']],
        ],
        'items' => [
            ['q' => 'Can I collect the print in person in Prague?', 'a' => 'Not yet. We send by Packeta to a pickup point or a parcel box; in Prague the nearest one is usually a few minutes from you and the parcel arrives there the next working day after dispatch. Once we open pickup, it will be another choice in the order.'],
            ['q' => 'How soon do I get the print?', 'a' => 'You see the print time of every model in advance; printing starts as soon as a printer is free. We dispatch on the day the print ends, on working days, and Packeta usually delivers within 1 to 2 working days in Czechia. What finishes at the weekend leaves on Monday.'],
            ['q' => 'Do I need my own model?', 'a' => 'No. Upload a model (STL, 3MF, OBJ, STEP), or make one free in your browser: a bust from a photo, a lithophane, a QR sign, an organiser to the size of your drawer, a 3D city map or a gift with a name. You can also download the model and print it at home.'],
            ['q' => 'Which materials do you print with?', 'a' => 'PLA, PETG, ASA and TPU; the material comparison in the calculation says which suits your model. You pick the colour from the spools loaded in the printers right now.'],
            ['q' => 'Do you print for businesses too?', 'a' => 'Yes, small batches of QR signs, logos, name tags and keyrings with a VAT document; enter the number of pieces in the calculation and see the price of the batch at once. For hundreds of pieces write to info@matplace.com.'],
            ['q' => 'What if I do not like the print or it is faulty?', 'a' => 'Before ordering you see the 3D preview, the dimensions, the print time and the price. The print is made to order, so it cannot be returned within 14 days without a reason; a defect (cracks, missing layers, a size other than ordered) you claim under the complaints procedure.'],
        ],
    ],
];
