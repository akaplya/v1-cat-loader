<?php

require 'vendor/autoload.php';


function getJWT($privateKey) {
    $payload = array(
        'exp' => time()+300, // valid for the next 5 minutes
    );
    return \Firebase\JWT\JWT::encode($payload, file_get_contents($privateKey), 'RS256');

}

function formatProduct(array $input): array
{
    ///parent_category_id	category_image	category_id	category_uid	category_name	category_description
    /// product_id	product_sku	product_name	product_description	product_short_description	product_thumbnail_url	product_price	product_currency
    $modifiedAt = new \DateTime('now');
    return [
        "sku" =>  $input['product_sku'],
        "status" => "Enabled",
        "storeViewCode" =>  "default",
        "storeCode" =>  "main_website_store",
        "websiteCode" => "base",
        "name" => $input['product_name'],
        "productId" => $input['product_id'],
        "type" => "simple",
        "description" => $input['product_description'],
        "shortDescription" => $input['product_short_description'],
        "urlKey" => strtolower($input['product_sku']),
        "visibility" => "Catalog, Search",
        "currency" => $input['product_currency'],
        "displayable" => true,
        "buyable" => true,
        "categoryData" => [
            [
                "categoryId" => ($input['category_id'] + 100),
                "categoryPath"=> strtolower(str_replace(' ', '-', $input['category_name'])),
                "productPosition" => 0
            ]
        ],
        "images" => [
            [
                "resource" => [
                    "url" => $input['product_thumbnail_url'],
                    "label" => "",
                    "roles" => [
                        "image",
                        "small_image",
                        "thumbnail"
                    ]
                ],
                "sortOrder" => "1"
            ]
        ],
        "inStock" => true,
        "lowStock" => false,
        "deleted" => false,
        "modifiedAt" => $modifiedAt->format('Y-m-d H:i:s')
    ];
}


function formatPrice(array $input): array
{
    $modifiedAt = new \DateTime('now');
    return [
        'productId' => $input['product_id'],
        'sku' => $input['product_sku'],
        'type' => 'SIMPLE',
        'customerGroupCode' => '0',
        'websiteCode' => 'base',
        'regular' =>  (float)$input['product_price'],
        'deleted' => false,
        'updatedAt' => $modifiedAt->format(\DateTime::ATOM)
    ];
}

function formatCategory(array $input): array
{
    $modifiedAt = new \DateTime('now');
    return [
        "categoryId" => (string)($input["category_id"]+100),
        "level" => "2",
        "includeInMenu" => 1,
        "isActive" => 1,
        "path" =>  "1/2/" . ($input["category_id"]+100),
        "name" => $input["category_name"],
        "description" => $input["category_description"],
        "image" => $input["category_image"],
        "storeViewCode" =>  "default",
        "storeCode" =>  "main_website_store",
        "websiteCode" => "base",
        'deleted' => false,
        'updatedAt' => $modifiedAt->format(\DateTime::ATOM)
    ];
}

function getCatalog(string $sourceFile): array
{
    $row = 1;
    $products = [];
    $output = [];
    $categories = [];
    if (($handle = fopen($sourceFile, "r")) !== false) {
        while (($data = fgetcsv($handle, 10000, ",")) !== false) {
            $num = count($data);
            if ($row == 1) {
                $header = $data;
            } else {
                for ($c=0; $c < $num; $c++) {
                    $output[$row][$header[$c]] = mb_convert_encoding($data[$c], 'UTF-8');
                }

                $products[$row] = formatProduct($output[$row]);
                $prices[$row] = formatPrice($output[$row]);
                $category = formatCategory($output[$row]);
                $categories[($category['categoryId'] + 100)] = $category;
//                echo $products[$row]['sku'] . PHP_EOL;
            }
            $row++;

        }
        fclose($handle);
    }
    $modifiedAt = new \DateTime('now');

    unset($categories['']);
    $children = array_keys($categories);
    $categories[] = [
        "categoryId" => "2",
        "level" => "1",
        "includeInMenu" => 1,
        "isActive" => 1,
        "path" =>  "1/2",
        "name" => "Main",
        "description" => "",
        "image" => null,
        "storeViewCode" =>  "default",
        "storeCode" =>  "main_website_store",
        "websiteCode" => "base",
        'deleted' => false,
        'children' => $children,
        'updatedAt' => $modifiedAt->format(\DateTime::ATOM)
    ];
    return [
        'products' => $products,
        'prices' => $prices,
        'categories' => array_values($categories)
    ];
}

$options = getopt('', ['public-key:', 'private-key-file:', 'source-file:', 'environment-id:']);


if(isset($options['public-key'])) {
    $publicKey = $options['public-key'];
}

if(isset($options['private-key-file'])) {
    $privateKeyFile = $options['private-key-file'];
}

if(isset($options['source-file'])) {
    $sourceFile = $options['source-file'];
}

if(isset($options['environment-id'])) {
    $environmentId = $options['environment-id'];
}


$data = getCatalog($sourceFile);

//var_dump($data);


$httpClient = new \GuzzleHttp\Client([
    'base_uri' => 'https://commerce.adobe.io'
]);

$options = [
    'headers' => [
        'Content-Type' => 'application/json',
        'x-api-key' => $publicKey,
        'x-gw-signature' => getJWT($privateKeyFile)
    ],
    'body' => '[{"website":{"websiteId":"1","websiteCode":"base","stores":[{"storeId":"1","storeCode":"main_website_store","storeViews":[{"storeViewId":"1","storeViewCode":"default"}]}]},"updatedAt":"2024-10-24T03:21:13+00:00","deleted":false}]'
];
$response = $httpClient->request(
    'POST',
    '/feeds/scopes/v2/' . $environmentId,
    $options
);

echo $response->getStatusCode() . PHP_EOL;

$options = [
    'headers' => [
        'Content-Type' => 'application/json',
        'x-api-key' => $publicKey,
        'x-gw-signature' => getJWT($privateKeyFile)
    ],
    'body' => '[{"id":"73","storeCode":"main_website_store","websiteCode":"base","storeViewCode":"default","attributeCode":"name","attributeType":"catalog_product","dataType":"varchar","multi":false,"label":"Product Name","frontendInput":"text","required":true,"unique":false,"global":false,"visible":true,"searchable":true,"filterable":false,"visibleInCompareList":false,"visibleInListing":true,"sortable":true,"visibleInSearch":false,"filterableInSearch":false,"searchWeight":5,"usedForRules":false,"boolean":false,"systemAttribute":false,"numeric":false,"attributeOptions":null,"deleted":false,"modifiedAt":"2024-10-24 03:57:06"},{"id":"77","storeCode":"main_website_store","websiteCode":"base","storeViewCode":"default","attributeCode":"price","attributeType":"catalog_product","dataType":"decimal","multi":false,"label":"Price","frontendInput":"price","required":true,"unique":false,"global":true,"visible":true,"searchable":true,"filterable":true,"visibleInCompareList":false,"visibleInListing":true,"sortable":true,"visibleInSearch":false,"filterableInSearch":false,"searchWeight":1,"usedForRules":false,"boolean":false,"systemAttribute":false,"numeric":true,"attributeOptions":null,"deleted":false,"modifiedAt":"2024-10-24 03:57:06"},{"id":"76","storeCode":"main_website_store","websiteCode":"base","storeViewCode":"default","attributeCode":"short_description","attributeType":"catalog_product","dataType":"text","multi":false,"label":"Short Description","frontendInput":"textarea","required":false,"unique":false,"global":false,"visible":true,"searchable":true,"filterable":false,"visibleInCompareList":true,"visibleInListing":true,"sortable":false,"visibleInSearch":false,"filterableInSearch":false,"searchWeight":1,"usedForRules":false,"boolean":false,"systemAttribute":false,"numeric":false,"attributeOptions":null,"deleted":false,"modifiedAt":"2024-10-24 03:57:06"}]'
];
$response = $httpClient->request(
    'POST',
    '/feeds/metadata/v2/metadata/' . $environmentId,
    $options
);

echo $response->getStatusCode() . PHP_EOL;


$data = getCatalog($sourceFile);
$products = json_encode(array_values($data['products']));
$prices = json_encode(array_values($data['prices']));
$categories = json_encode(array_values($data['categories']));

$options = [
    'headers' => [
        'Content-Type' => 'application/json',
        'x-api-key' => $publicKey,
        'x-gw-signature' => getJWT($privateKeyFile)
    ],
    'body' => $products
];
$response = $httpClient->request(
    'POST',
    '/feeds/catalog/v2/products/' . $environmentId,
    $options
);

echo $response->getStatusCode() . PHP_EOL;


$options = [
    'headers' => [
        'Content-Type' => 'application/json',
        'x-api-key' => $publicKey,
        'x-gw-signature' => getJWT($privateKeyFile)
    ],
    'body' => $prices
];
$response = $httpClient->request(
    'POST',
    '/feeds/products/v1/prices/' . $environmentId,
    $options
);

echo $response->getStatusCode() . PHP_EOL;


$options = [
    'headers' => [
        'Content-Type' => 'application/json',
        'x-api-key' => $publicKey,
        'x-gw-signature' => getJWT($privateKeyFile)
    ],
    'body' => $categories
];
$response = $httpClient->request(
    'POST',
    '/feeds/categories/v1/' . $environmentId,
    $options
);



echo $response->getStatusCode() . PHP_EOL;

print_r($categories);
?>