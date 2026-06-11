<?php
require __DIR__ . '/../vendor/autoload.php';

// Find where validation is triggered in API Platform.
// Let's print out the definition of ApiPlatform\Symfony\Validator\State\ValidateProcessor if it exists.
if (class_exists('ApiPlatform\Symfony\Validator\State\ValidateProcessor')) {
    $r = new ReflectionClass('ApiPlatform\Symfony\Validator\State\ValidateProcessor');
    echo "Found ValidateProcessor!\n";
    echo $r->getFileName() . "\n";
    // Read the file contents.
    echo file_get_contents($r->getFileName());
} else {
    echo "ValidateProcessor not found.\n";
}
