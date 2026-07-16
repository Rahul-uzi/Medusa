<?php
require_once 'D:/xampp/htdocs/Medusa/api/config.php';

try {
    $pdo->beginTransaction();
    $pdo->prepare("UPDATE food_items SET image_url = 'murgh_yakhani_shorba.jpg' WHERE id = 9")->execute();
    echo "Updated ID 9 with image murgh_yakhani_shorba.jpg\n";
    $pdo->prepare("UPDATE food_items SET image_url = 'burmese_khao_suey.jpg' WHERE id = 27")->execute();
    echo "Updated ID 27 with image burmese_khao_suey.jpg\n";
    $pdo->prepare("UPDATE food_items SET image_url = 'hot_and_sour_soup.jpg' WHERE id = 4")->execute();
    echo "Updated ID 4 with image hot_and_sour_soup.jpg\n";
    $pdo->prepare("UPDATE food_items SET image_url = 'lemon_coriander.jpg' WHERE id = 6")->execute();
    echo "Updated ID 6 with image lemon_coriander.jpg\n";
    $pdo->prepare("UPDATE food_items SET image_url = 'himalayan_thukpa.jpg' WHERE id = 38")->execute();
    echo "Updated ID 38 with image himalayan_thukpa.jpg\n";
    $pdo->prepare("UPDATE food_items SET image_url = 'dal_shorba.jpg' WHERE id = 358")->execute();
    echo "Updated ID 358 with image dal_shorba.jpg\n";
    $pdo->prepare("UPDATE food_items SET image_url = 'roasted_tomato_basil.jpg' WHERE id = 7")->execute();
    echo "Updated ID 7 with image roasted_tomato_basil.jpg\n";
    $pdo->prepare("UPDATE food_items SET image_url = 'cream_of_mushroom.jpg' WHERE id = 1")->execute();
    echo "Updated ID 1 with image cream_of_mushroom.jpg\n";

    $pdo->commit();
    echo "=== SOUP IMAGES SYNCED IN DB ===\n";
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Error: " . $e->getMessage() . "\n");
}
?>