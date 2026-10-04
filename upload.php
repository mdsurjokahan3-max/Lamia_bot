<?php
header('Content-Type: application/json; charset=utf-8');

/*
|--------------------------------------------------------------------------
| Cloudinary Configuration
|--------------------------------------------------------------------------
| IMPORTANT:
| API Secret কখনো HTML/JavaScript-এ দেবেন না।
|--------------------------------------------------------------------------
*/

$CLOUDINARY_CLOUD_NAME = "ialc7f5k";
$CLOUDINARY_API_KEY    = "YOUR_API_KEY";
$CLOUDINARY_API_SECRET = "YOUR_API_SECRET";


/*
|--------------------------------------------------------------------------
| Only POST requests
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        "success" => false,
        "message" => "Invalid request method."
    ]);
    exit;
}


/*
|--------------------------------------------------------------------------
| Check file
|--------------------------------------------------------------------------
*/
if (!isset($_FILES['file'])) {
    echo json_encode([
        "success" => false,
        "message" => "No image file received."
    ]);
    exit;
}

$file = $_FILES['file'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode([
        "success" => false,
        "message" => "File upload failed. Error code: " . $file['error']
    ]);
    exit;
}


/*
|--------------------------------------------------------------------------
| Maximum file size: 5 MB
|--------------------------------------------------------------------------
*/
$MAX_SIZE = 5 * 1024 * 1024;

if ($file['size'] > $MAX_SIZE) {
    echo json_encode([
        "success" => false,
        "message" => "Image size must be 5 MB or less."
    ]);
    exit;
}


/*
|--------------------------------------------------------------------------
| Validate MIME type
|--------------------------------------------------------------------------
*/
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowedTypes = [
    'image/jpeg',
    'image/png',
    'image/webp'
];

if (!in_array($mime, $allowedTypes, true)) {
    echo json_encode([
        "success" => false,
        "message" => "Only JPG, PNG and WEBP images are allowed."
    ]);
    exit;
}


/*
|--------------------------------------------------------------------------
| Generate Cloudinary signature
|--------------------------------------------------------------------------
|
| Cloudinary signed upload:
|
| timestamp + API Secret
|
*/

$timestamp = time();

$paramsToSign = [
    "timestamp" => $timestamp
];

ksort($paramsToSign);

$signatureString = "";

foreach ($paramsToSign as $key => $value) {
    $signatureString .= $key . "=" . $value . "&";
}

$signatureString = rtrim($signatureString, "&");

$signature = sha1($signatureString . $CLOUDINARY_API_SECRET);


/*
|--------------------------------------------------------------------------
| Cloudinary Upload URL
|--------------------------------------------------------------------------
*/

$uploadUrl =
    "https://api.cloudinary.com/v1_1/" .
    rawurlencode($CLOUDINARY_CLOUD_NAME) .
    "/image/upload";


/*
|--------------------------------------------------------------------------
| CURL Upload
|--------------------------------------------------------------------------
*/

$postFields = [
    'file'      => new CURLFile(
        $file['tmp_name'],
        $mime,
        $file['name']
    ),
    'api_key'   => $CLOUDINARY_API_KEY,
    'timestamp' => $timestamp,
    'signature' => $signature
];


$ch = curl_init($uploadUrl);

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $postFields,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 120,
    CURLOPT_CONNECTTIMEOUT => 30
]);

$response = curl_exec($ch);

$curlError = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);


/*
|--------------------------------------------------------------------------
| CURL error
|--------------------------------------------------------------------------
*/

if ($response === false || !empty($curlError)) {
    echo json_encode([
        "success" => false,
        "message" => "Cloudinary connection failed.",
        "error" => $curlError
    ]);
    exit;
}


/*
|--------------------------------------------------------------------------
| Decode Cloudinary response
|--------------------------------------------------------------------------
*/

$data = json_decode($response, true);

if ($httpCode >= 200 && $httpCode < 300 && isset($data['secure_url'])) {

    echo json_encode([
        "success" => true,
        "message" => "Image uploaded successfully.",
        "url" => $data['secure_url'],
        "secure_url" => $data['secure_url'],
        "public_id" => $data['public_id'] ?? "",
        "width" => $data['width'] ?? 0,
        "height" => $data['height'] ?? 0
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Cloudinary error
|--------------------------------------------------------------------------
*/

echo json_encode([
    "success" => false,
    "message" => "Cloudinary upload failed.",
    "cloudinary_response" => $data
]);

exit;
?>
