<?php
require_once 'D:/xampp/htdocs/Medusa/api/config.php';

try {
    $pdo->beginTransaction();
    $pdo->prepare("UPDATE food_items SET image_url = 'burrata_and_berry_salad.jpg' WHERE id = 10")->execute();
    echo "Updated ID 10 with image burrata_and_berry_salad.jpg\n";
    $pdo->prepare("UPDATE food_items SET image_url = 'healthy_organic_quinoa.jpg' WHERE id = 13")->execute();
    echo "Updated ID 13 with image healthy_organic_quinoa.jpg\n";
    $pdo->prepare("UPDATE food_items SET image_url = 'som_tom_salad.jpg' WHERE id = 14")->execute();
    echo "Updated ID 14 with image som_tom_salad.jpg\n";
    $pdo->prepare("UPDATE food_items SET image_url = 'classical_caesar_salad.jpg' WHERE id = 11")->execute();
    echo "Updated ID 11 with image classical_caesar_salad.jpg\n";

    $pdo->commit();
    echo "=== SALAD IMAGES SYNCED IN DB ===\n";
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Error: " . $e->getMessage() . "\n");
}
?>