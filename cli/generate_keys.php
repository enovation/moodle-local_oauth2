<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * CLI script to generate RSA key pairs for OAuth2 OpenID Connect.
 *
 * This script generates RSA key pairs needed for signing OpenID Connect ID tokens.
 * Keys are stored in the local_oauth2_public_key table.
 *
 * Usage:
 *   php local/oauth2/cli/generate_keys.php [--client-id=<client_id>] [--force]
 *
 * Options:
 *   --client-id       Optional client ID to associate keys with (default: NULL for all clients)
 *   --force           Force regeneration of keys even if they already exist
 *   --help, -h        Display this help message
 *
 * @package    local_oauth2
 * @author     Lai Wei <lai.wei@enovation.ie>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2026 Enovation Solutions
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

// Get command line options.
[$options, $unrecognized] = cli_get_params(
    [
        'help' => false,
        'client-id' => null,
        'force' => false,
    ],
    [
        'h' => 'help',
    ]
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    $help = <<<EOT
Generate RSA key pairs for OAuth2 OpenID Connect.

This script generates RSA key pairs needed for signing OpenID Connect ID tokens.
Keys are stored in the local_oauth2_public_key table.

Usage:
  php local/oauth2/cli/generate_keys.php [--client-id=<client_id>] [--force]

Options:
  --client-id       Optional client ID to associate keys with (default: NULL for all clients)
  --force           Force regeneration of keys even if they already exist
  --help, -h        Display this help message

Examples:
  # Generate default keys for all clients
  php local/oauth2/cli/generate_keys.php

  # Generate keys for a specific client
  php local/oauth2/cli/generate_keys.php --client-id=myclient

  # Force regenerate keys (overwrites existing)
  php local/oauth2/cli/generate_keys.php --force

EOT;
    echo $help;
    exit(0);
}

$clientid = $options['client-id'];
$force = $options['force'];

cli_heading('OAuth2 OpenID Connect Key Generation');

// Check if keys already exist.
$conditions = [];
if ($clientid) {
    $conditions['client_id'] = $clientid;
    cli_writeln("Generating keys for client: $clientid");
} else {
    $conditions['client_id'] = '';
    cli_writeln('Generating default keys for all clients');
}

if ($DB->record_exists('local_oauth2_public_key', $conditions) && !$force) {
    cli_error('Keys already exist. Use --force to regenerate.', 1);
}

// Generate RSA key pair.
cli_writeln('Generating RSA key pair (2048 bits)...');

$config = [
    'private_key_bits' => 2048,
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
];

$res = openssl_pkey_new($config);
if ($res === false) {
    cli_error('Failed to generate RSA key pair: ' . openssl_error_string(), 1);
}

// Extract private key.
if (!openssl_pkey_export($res, $privatekey)) {
    cli_error('Failed to export private key: ' . openssl_error_string(), 1);
}

// Extract public key.
$publickeydetails = openssl_pkey_get_details($res);
if ($publickeydetails === false) {
    cli_error('Failed to get public key details: ' . openssl_error_string(), 1);
}
$publickey = $publickeydetails['key'];

// Store or update keys in database.
$record = new stdClass();
$record->client_id = $clientid ? $clientid : '';
$record->public_key = $publickey;
$record->private_key = $privatekey;
$record->encryption_algorithm = 'RS256';

if ($force && $existing = $DB->get_record('local_oauth2_public_key', $conditions)) {
    $record->id = $existing->id;
    $DB->update_record('local_oauth2_public_key', $record);
    cli_writeln('✓ Keys regenerated successfully (ID: ' . $record->id . ')');
} else {
    $record->id = $DB->insert_record('local_oauth2_public_key', $record);
    cli_writeln('✓ Keys generated and stored successfully (ID: ' . $record->id . ')');
}

cli_writeln('');
cli_writeln('Key details:');
cli_writeln('  Algorithm: RS256');
cli_writeln('  Key size: 2048 bits');
if ($clientid) {
    cli_writeln('  Client ID: ' . $clientid);
} else {
    cli_writeln('  Client ID: (empty - default for all clients)');
}

cli_writeln('');
cli_writeln('Public key preview:');
cli_writeln(substr($publickey, 0, 100) . '...');

exit(0);
