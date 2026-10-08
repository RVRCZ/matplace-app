<?php

return [
    'title' => 'Profit from selling 3D prints: Etsy, Fler and market fees',
    'description' => 'What a sale leaves you: the price less the fees of Etsy, Fler, a shop or a market, shipping, VAT and the cost of the piece. Three prices and the break-even.',
    'h1' => 'How much of a sale you really keep',
    'intro' => [
        'The calculator takes the selling price, the cost of the piece and the platform and subtracts everything someone takes on the way: the listing fee, the commission on the sale, payment processing, currency conversion, a shop\'s monthly fee spread over the pieces sold or a stall at a market, the shipping you pay on top, and VAT if you are registered. What is left is the profit per piece, the margin of the price and how many pieces a month cover your fixed costs.',
        'The platforms\' rates are stored with a date and a source and the page says beside the result which day they apply to; compare them with the current price list before deciding. Three prices side by side show what a tenth less or more does. The cost of the piece comes from the cost tool and the result goes on into the selling plan. Everything is computed in the browser, nothing is stored.',
    ],
    'steps' => [
        ['name' => 'Choose where you sell', 'text' => 'Etsy, Fler, Shopify, your own shop, a market or fair, or the matplace farm. Tick VAT registered and selling in a foreign currency.'],
        ['name' => 'Enter the sale', 'text' => 'The selling price, the cost of the piece, a discount, the shipping charged to the customer and the shipping you really pay.'],
        ['name' => 'Enter the month', 'text' => 'How many pieces you sell a month and your fixed costs; for a market the stall fee and the pieces a day.'],
        ['name' => 'Read the result', 'text' => 'Profit per piece, margin, net income, a breakdown of the fees, the break-even and three prices side by side. Send it on into the selling plan.'],
    ],
    'faq' => [
        ['q' => 'Which Etsy fees do you count?', 'a' => 'The listing fee of 0.20 USD, the 6.5 % commission on the price including shipping, payment processing for the Czech Republic at 4 % + 10 Kč and the 2.5 % currency conversion when you sell in euros or dollars. Offsite Ads are not counted; the page shows the date of the rates.'],
        ['q' => 'And Fler, Shopify and an own shop?', 'a' => 'Fler takes an 11 % commission on the goods. Shopify a monthly plan and a payment gateway with Shopify\'s fee for a third-party gateway. An own shop a rental (Shoptet) and a payment gateway. Every rate carries its date and a link to the price list.'],
        ['q' => 'How is VAT counted?', 'a' => 'A registered seller pays 21 % on the price including shipping; the calculator takes it out of what the customer paid. If you are not registered, leave the box empty; the platforms handle VAT on their fees their own way.'],
        ['q' => 'What is the break-even?', 'a' => 'How many pieces a month you must sell so the profit per piece pays the fixed costs: rent, software, advertising. If the profit per piece is zero or negative, they are never covered.'],
        ['q' => 'Where does the cost of the piece come from?', 'a' => 'From the cost tool, which adds up filament, power, wear, failed prints and work. When the matplace farm prints, your cost is our price from the calculation.'],
        ['q' => 'Are the rates current?', 'a' => 'They are stored with a date and a source, and the page shows the date beside the result. Platforms change their rates; compare them with their price list before deciding.'],
    ],
    'examples' => [],
];
