<?php

$bearerToken = "61722080|zDXwM1xlUp1N0wl6yQnmjAVYrIuzZOBb5MKOOW0K";
$clientId = "7191980";

// 1️⃣ Fetch all FanBoxes
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => "https://api.fancourier.ro/reports/pickup-points?type=fanbox",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer {$bearerToken}",
        "Content-Type: application/json"
    ]
]);
$response = curl_exec($curl);
curl_close($curl);

$fanboxes = json_decode($response, true);

// Check if we have any FanBoxes
if (empty($fanboxes)) {
    die("No FanBoxes found or API returned an error:\n" . $response);
}
else{
 //  echo "<pre>"; print_R($fanboxes['data'][0]); die('asd');
}

// For demo, pick the first FanBox
$selectedFanBox = $fanboxes['data'][0]['id'];
$fanboxId = $fanboxes['data'][0]['id'];
$fanboxOption = $selectedFanBox['data']['0']['drawer']['0']['type']; // Must be exact string from API
$country = $fanboxes['data']['0']['address']['county'];
$locality = $fanboxes['data']['0']['address']['locality'];
$street = $fanboxes['data']['0']['address']['street'];
echo "Selected FanBox ID: $fanboxId\n";
echo "FanBox type for AWB: $fanboxOption\n";
echo "FanBox type for country: $country\n";
echo "FanBox type for locality: $locality\n";
echo "FanBox type for Street: $street\n";
echo "------FanBox type for fanboxOption: $fanboxOption\n";

// 2️⃣ Prepare AWB payload
$payload = [
    "clientId" => $clientId,
    "shipments" => [
        [
            "info" => [
                "service" => "FANbox",
                "weight" => 2,
                "payment" => "recipient",
                "cashRepayment" => 100,
                "packages" => [
                    "parcel" => 1,
                    "envelopes" => 0
                ],
                "options" => [
                    "fanBoxDropOffOrPickupOption" => $fanboxOption
                ]
            ],
            "sender" => [
                "name" => "My Company SRL",
                "phone" => "0712345678",
                "address" => [
                    "county" => "Bucuresti",
                    "locality" => "Sector 1",
                    "street" => "Str. Aviatorilor 10"
                ]
            ],
            "recipient" => [
                "name" => "John Doe",
                "phone" => "0722334455",
                "address" => [
                    "county" => $country,
                    "locality" => $locality,
                    "street" => $street,
                    "fanbox_id" => $fanboxId
                ]
            ]


        ]
    ]
];

// 3️⃣ Submit AWB
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => 'https://api.fancourier.ro/intern-awb',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_PRETTY_PRINT),
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer {$bearerToken}",
        "Content-Type: application/json"
    ],
]);

$awbResponse = curl_exec($curl);
curl_close($curl);

echo "AWB Response:\n";
echo "<pre>"; print_R($awbResponse);

?>

