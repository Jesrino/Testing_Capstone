<?php
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Return available services
        $services = [
            [
                'id' => 'dental-cleaning',
                'name' => 'Dental Cleaning',
                'description' => 'Professional teeth cleaning to remove plaque, tartar, and stains. Includes polishing and fluoride treatment.',
                'price' => '₱1,500 - ₱2,500',
                'duration' => '30-45 minutes',
                'benefits' => [
                    'Removes plaque and tartar buildup',
                    'Prevents gum disease and cavities',
                    'Fresher breath and brighter smile',
                    'Early detection of dental issues'
                ]
            ],
            [
                'id' => 'braces-orthodontics',
                'name' => 'Braces & Orthodontics',
                'description' => 'Straighten your teeth and improve your bite with traditional metal braces, ceramic braces, or clear aligners.',
                'price' => '₱25,000 - ₱80,000',
                'duration' => '6-24 months treatment',
                'benefits' => [
                    'Improved smile aesthetics',
                    'Better bite alignment',
                    'Easier cleaning and maintenance',
                    'Reduced risk of dental problems'
                ]
            ],
            [
                'id' => 'dental-implants',
                'name' => 'Dental Implants',
                'description' => 'Permanent solution for missing teeth. Titanium implants fused to the jawbone provide a stable foundation.',
                'price' => '₱50,000 - ₱150,000',
                'duration' => '3-6 months (including healing)',
                'benefits' => [
                    'Permanent tooth replacement',
                    'Restores natural chewing ability',
                    'Prevents bone loss',
                    'Natural-looking and feeling'
                ]
            ],
            [
                'id' => 'tooth-fillings',
                'name' => 'Tooth Fillings',
                'description' => 'Restore damaged teeth with durable composite or amalgam fillings. We use tooth-colored materials.',
                'price' => '₱800 - ₱3,000',
                'duration' => '30-60 minutes',
                'benefits' => [
                    'Restores tooth function',
                    'Prevents further decay',
                    'Natural-looking results',
                    'Long-lasting durability'
                ]
            ],
            [
                'id' => 'tooth-extraction',
                'name' => 'Tooth Extraction',
                'description' => 'Safe and painless removal of damaged or problematic teeth. Includes simple extractions and surgical extractions.',
                'price' => '₱1,000 - ₱5,000',
                'duration' => '15-45 minutes',
                'benefits' => [
                    'Relieves pain from damaged teeth',
                    'Prevents spread of infection',
                    'Makes space for orthodontic treatment',
                    'Improves overall oral health'
                ]
            ],
            [
                'id' => 'removable-dentures',
                'name' => 'Removable Dentures',
                'description' => 'Custom-fitted dentures for partial or complete tooth replacement. Comfortable, natural-looking solutions.',
                'price' => '₱15,000 - ₱45,000',
                'duration' => '2-4 weeks (including fittings)',
                'benefits' => [
                    'Restores ability to eat and speak',
                    'Improves facial appearance',
                    'Easy to clean and maintain',
                    'Affordable tooth replacement option'
                ]
            ],
            [
                'id' => 'root-canal',
                'name' => 'Root Canal Treatment',
                'description' => 'Save infected or damaged teeth by removing infected pulp and sealing the tooth.',
                'price' => '₱3,000 - ₱8,000',
                'duration' => '60-90 minutes',
                'benefits' => [
                    'Saves natural tooth',
                    'Eliminates pain and infection',
                    'Restores normal chewing function',
                    'Prevents spread of infection'
                ]
            ],
            [
                'id' => 'teeth-whitening',
                'name' => 'Teeth Whitening',
                'description' => 'Brighten your smile with professional whitening treatments. In-office laser whitening or take-home kits.',
                'price' => '₱2,000 - ₱5,000',
                'duration' => '30-60 minutes',
                'benefits' => [
                    'Brighter, more confident smile',
                    'Removes stains from food and drinks',
                    'Safe and effective whitening',
                    'Long-lasting results'
                ]
            ]
        ];

        echo json_encode(['services' => $services]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}
?>
