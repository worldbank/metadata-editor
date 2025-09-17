<?php
/**
 * Standalone CLI Password Helper Script
 * 
 * 
 * Usage:
 * php password_helper.php generate <password>
 * php password_helper.php verify <password> <hash>
 */

// Only allow CLI access
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.\n");
}

// Check if we have the required arguments
if ($argc < 2) {
    show_help();
    exit(1);
}

$command = $argv[1];

switch ($command) {
    case 'generate':
        if ($argc < 3) {
            echo "Error: Password required\n";
            echo "Usage: php application/utils/password_helper.php generate <password>\n";
            echo "Example: php application/utils/password_helper.php generate type-new-password\n";            
            exit(1);
        }
        generate_password_hash($argv[2]);
        break;
        
    case 'verify':
        if ($argc < 4) {
            echo "Error: Password and hash required\n";
            echo "Usage: php application/utils/password_helper.php verify <password> <hash>\n";
            exit(1);
        }
        verify_password($argv[2], $argv[3]);
        break;
        
    case 'help':
    case '--help':
    case '-h':
        show_help();
        break;
        
    default:
        echo "Error: Unknown command '$command'\n\n";
        show_help();
        exit(1);
}

function generate_password_hash($password) {
    // Load the password hasher library
    require_once(__DIR__ . '/../libraries/PasswordHash.php');
    
    // Create password hasher instance (same as in Ion_auth_model)
    $hasher = new PasswordHash(8, FALSE);
    
    // Check password length (72 character limit)
    if (strlen($password) > 72) {
        echo "Error: Password too long (max 72 characters)\n";
        exit(1);
    }
    
    // Generate hash using the same method as Ion_auth_model
    $hash = $hasher->HashPassword($password);
    
    if ($hash === FALSE || $hash === '*') {
        echo "Error: Failed to generate password hash\n";
        exit(1);
    }
    
    echo "Password Hash Generated:\n";
    echo "Password: " . $password . "\n";
    echo "Hash: " . $hash . "\n";
    echo "\n";
    echo "To update a user's password in the database:\n";
    echo "UPDATE users SET password = '" . $hash . "' WHERE email = 'user@example.com';\n";
}

function verify_password($password, $hash) {
    // Load the password hasher library
    require_once(__DIR__ . '/../libraries/PasswordHash.php');
    
    // Create password hasher instance (same as in Ion_auth_model)
    $hasher = new PasswordHash(8, FALSE);
    
    // Check password length (72 character limit)
    if (strlen($password) > 72) {
        echo "Error: Password too long (max 72 characters)\n";
        exit(1);
    }
    
    // Use secure password hashing only
    if ($hasher->CheckPassword($password, $hash)) {
        echo "Password verification: VALID\n";
        echo "Hash type: Portable PHP password hashing framework (secure)\n";
        return;
    }
    
    echo "Password verification: INVALID\n";
}

function show_help() {
    echo "Metadata EditorPassword Helper CLI Tool\n";
    echo "=============================\n\n";
    echo "Uses the same password_hasher library as Ion_auth_model for consistency.\n";
    echo "Based on the Portable PHP password hashing framework.\n\n";
    echo "Available commands:\n\n";
    echo "1. Generate secure password hash:\n";
    echo "   php application/utils/password_helper.php generate <password>\n\n";
    echo "2. Verify password against hash:\n";
    echo "   php application/utils/password_helper.php verify <password> <hash>\n\n";
    echo "3. Show this help:\n";
    echo "   php application/utils/password_helper.php help\n\n";
    echo "Examples:\n";
    echo "php application/utils/password_helper.php generate type-new-password\n\n\n";
    echo "Manual Database Update:\n";
    echo "After generating a hash, manually update the database:\n";
    echo "UPDATE users SET password = '<generated_hash>' WHERE email = 'user@example.com';\n\n";
}
