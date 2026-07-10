<?php
header('Content-Type: application/json');

// Using the API Key and Place ID you provided
$google_api_key = "AIzaSyDj3kNXQgPhCLT4iEYdBAgsctsRqEkS6pw";
$place_id = "ChIJ3QPTMADvDzkRztLrdeXdGsg"; // Place ID for Medusa Bar & Lounge found via API

if (empty($google_api_key) || empty($place_id)) {
    echo json_encode(['error' => 'API Key or Place ID not configured.']);
    exit;
}

// Set up caching to make the reviews load instantly and only fetch from Google every 24 hours
$cache_file = __DIR__ . '/reviews_cache.json';
$cache_time = 24 * 60 * 60; // 24 hours in seconds

// If a valid cache file exists, serve it instantly without waiting for Google!
if (file_exists($cache_file) && (time() - filemtime($cache_file) < $cache_time)) {
    echo file_get_contents($cache_file);
    exit;
}

// If the cache is expired or missing, fetch fresh data from Google
$url = "https://places.googleapis.com/v1/places/" . $place_id . "?fields=reviews";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-Goog-Api-Key: ' . $google_api_key,
    'X-Goog-FieldMask: reviews'
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 200) {
    $data = json_decode($response, true);
    
    if (isset($data['reviews'])) {
        $reviews = $data['reviews'];
        
        $formatted_reviews = [];
        foreach ($reviews as $review) {
            if (!empty($review['text']['text'])) {
                $formatted_reviews[] = [
                    'author_name' => $review['authorAttribution']['displayName'],
                    'profile_photo_url' => $review['authorAttribution']['photoUri'],
                    'rating' => $review['rating'],
                    'text' => $review['text']['text'],
                    'time' => $review['publishTime']
                ];
            }
        }
        
        $final_json = json_encode(['status' => 'success', 'reviews' => $formatted_reviews]);
        
        // Save the result to the cache file so future page loads are lightning fast
        file_put_contents($cache_file, $final_json);
        
        echo $final_json;
    } else {
         echo json_encode(['status' => 'success', 'reviews' => []]);
    }
} else {
    // If Google fails (e.g., API limits), try to serve the expired cache as a fallback so the site doesn't break
    if (file_exists($cache_file)) {
        echo file_get_contents($cache_file);
    } else {
        // ULTIMATE FAILSAFE: If Google is down AND the cache was accidentally deleted, serve these 3 hardcoded authentic reviews!
        $fallback_reviews = [
            [
                'author_name' => 'Raghav Goel',
                'profile_photo_url' => 'https://lh3.googleusercontent.com/a-/ALV-UjVxPfdZwTa6M_X0Iqe9h3rL5qhOO1FCpijn0KIc2JUtIUWc71mz=s128-c0x00000000-cc-rp-mo-ba6',
                'rating' => 5,
                'text' => 'Its one of the most beautiful cafe i have seen in a long time. Stunning interiors and design. I tried the mushroom dim sums and it was juice and too good. Must visit here for tandoori mushroom tikka and paneer tikka, it’s one of the best i had in town. Also, tried the spice n nice that has a good flavour of peach and mint. Don’t miss out the sushi here. Highly recommended',
                'time' => '2025-12-17T08:38:00.601483195Z'
            ],
            [
                'author_name' => 'Sanbir Kapoor',
                'profile_photo_url' => 'https://lh3.googleusercontent.com/a-/ALV-UjXJkMcSmDD7nsLm-X0cyaEXW3nAQAFqOtnJIzxBwSH1795NZjVY=s128-c0x00000000-cc-rp-mo-ba8',
                'rating' => 5,
                'text' => 'Amazing ambiance. Nice and courteous staff. We ordered soup as that was evening but soup were delicious and Amazing. Must visit place',
                'time' => '2025-11-25T11:52:01.567962806Z'
            ],
            [
                'author_name' => 'pragati thakur',
                'profile_photo_url' => 'https://lh3.googleusercontent.com/a-/ALV-UjXtOLxh578EkyvktltyNqpVBweyaMbl17ArzQhEaKZYuYg_thw=s128-c0x00000000-cc-rp-mo-ba3',
                'rating' => 5,
                'text' => 'Lovely experience at Cafe Medusa! The food was delicious, the ambience was warm and inviting, and the servers were extremely polite and accommodating. We tried the platter and the burrata pizza—both were excellent and full of flavor. Definitely a place worth visiting for a relaxed and enjoyable meal.',
                'time' => '2026-04-02T14:00:23.207743520Z'
            ]
        ];
        
        echo json_encode(['status' => 'success', 'reviews' => $fallback_reviews]);
    }
}
?>
