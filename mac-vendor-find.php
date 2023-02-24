<?php
// Arguments: -f <file_name> | for check all mac addresses from a a file;
//                           | each mac on a new line
//
// First argument can be a single mac address.
//

$local_db_file_name     = "local_vendor_db.txt";
$shortopts              = "";
$shortopts             .= "m:";
$shortopts             .= "f:";
$shortopts             .= "h";
$longopts               = array(
    "mac:",     // Required value
    "file:",    // Required value
    "help",     // No value
);
$options                = getopt($shortopts, $longopts);

if (empty($options)) {
    // No arguments passed
    print_help_message();
    exit(1);
} else if ((isset($options['m']) || isset($options['mac'])) && (isset($options['f']) || isset($options['file']))) {
    // Chose between -m and -f
    print_r("Chose between -f and -m\n\n");
    print_help_message();
    exit(1);
} else {
    foreach (array_keys($options) as $opt) switch (true) {
        case ($opt == 'mac' || $opt == 'm'):
            // Single MAC search
            echo "Mac: $options[$opt]\n";
            mac_search($options[$opt], $local_db_file_name);
            break;

        case ($opt == 'file' || $opt == 'f'):
            // Bulk MAC search with file parameter provided
            echo "File: $options[$opt]\n";
            bulk_search($options[$opt], $local_db_file_name);
            break;

        case ($opt == 'help' || $opt == 'h'):
            print_help_message();
            exit(1);
    }
}

// ----------------Functions---------------------------

// Bulk Search
function bulk_search($file_name, $local_db_file_name)
{
    $mac_list = file("$file_name", FILE_IGNORE_NEW_LINES);
    natsort($mac_list);
    //$tmp_file = "$argv[1]" . ".tmp";
    //file_put_contents($tmp_file, implode("\n", $mac_list));

    $local_db_updated       = false;
    $first_run              = true;
    $records_updated        = 0;
    $counter                = 0;

    foreach ($mac_list as $mac_address) {
        $local_result   = null;
        $remote_result  = null;
        $counter++;
        echo "$counter : ";

        // check if local database was modifiet through this run
        if ($local_db_updated || $first_run) {
            $local_db               = fopen("$local_db_file_name", "a+");
            $headers                = fgetcsv($local_db);
            if (!$headers) {
                fwrite($local_db, "\"id\",\"name\"\n");
                echo "Database initialised. Run again!\n";
                exit;
            }
            $new_vendor_array       = array();
            while ($row = fgetcsv($local_db)) {
                if (!$row[0]) continue;
                // "id","name"
                $nextItem = array();
                for ($i = 0; $i < 2; ++$i) {
                    $nextItem[$headers[$i]] = $row[$i];
                }
                $new_vendor_array[] = $nextItem;
            }
            fclose($local_db);
            $first_run = false;
        }

        // Extract vendor id from mac_address that is about to be searched
        $vendor_id = substr(str_replace(array(".", ":", "-"), "", $mac_address), 0, 6);
        // Find in local database if there is already defined vendor name for this vendor_id
        foreach ($new_vendor_array as $vendor) {
            if ($vendor['id'] == "$vendor_id") {
                $local_result = $vendor['name'];
                break;
            } else {
                $local_result = null;
            }
        }

        if (is_null($local_result)) {
            // search with api
            sleep(1.5);  // sleep 1.5 - api is limited to query /s
            $url    = "https://api.macvendors.com/" . urlencode($mac_address);
            $ch     = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $response = curl_exec($ch);
            // $response = "Cisco Fake";
            if ($response) {
                $remote_result = "$response";
            } else {
                $remote_result = null;
            }

            // if not null save new vendor in db
            if (!is_null($remote_result)) {
                $local_db = fopen($local_db_file_name, "a+");
                fputs($local_db, "\"$vendor_id\"" . "," . "\"$remote_result\"\n");
                fclose($local_db);
                // we need to know if local db was updated to reload the file
                $local_db_updated = true;
                $records_updated++;
            }

            $vendor_name = "(RDB) " . $remote_result;
        } else {
            $vendor_name = "(LDB) " . $local_result;
        }
        // print vendor name
        echo "$mac_address : $vendor_name \n";
    }

    // Print informational message about new records inserted into local database
    if ($records_updated > 0) {
        echo "\nLocal DB was updated with: $records_updated Vendor ID/Name\n";
    }
}

// Single MAC Search
function mac_search($mac_address, $local_db_file_name)
{
    $local_db_updated       = false;
    $first_run              = true;
    $records_updated        = 0;
    $counter                = 0;
    $local_result           = null;
    $remote_result          = null;
    $counter++;

    // Load local Database if exist or Init a new one
    $local_db               = fopen($local_db_file_name, "a+");
    $headers                = fgetcsv($local_db);
    if (!$headers) {
        fwrite($local_db, "\"id\",\"name\"\n");
        echo "Database initialised. Run again!\n";
        exit;
    }
    $new_vendor_array       = array();
    while ($row = fgetcsv($local_db)) {
        if (!$row[0]) continue;
        // "id","name"
        $nextItem = array();
        for ($i = 0; $i < 2; ++$i) {
            $nextItem[$headers[$i]] = $row[$i];
        }
        $new_vendor_array[] = $nextItem;
    }
    fclose($local_db);

    // Extract vendor id from mac_address that is about to be searched
    $vendor_id = substr(str_replace(array(".", ":", "-"), "", $mac_address), 0, 6);
    // Find in local database if there is already defined vendor name for this vendor_id
    foreach ($new_vendor_array as $vendor) {
        if ($vendor['id'] == "$vendor_id") {
            $local_result = $vendor['name'];
            break;
        } else {
            $local_result = null;
        }
    }

    if (is_null($local_result)) {
        // search with api
        sleep(1.5);  // sleep 1.5 - api is limited to query /s
        $url    = "https://api.macvendors.com/" . urlencode($mac_address);
        $ch     = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($ch);
        // $response = "Cisco Fake";
        if ($response) {
            $remote_result = "$response";
        } else {
            $remote_result = null;
        }

        // if not null save new vendor in db
        if (!is_null($remote_result)) {
            $local_db = fopen($local_db_file_name, "a+");
            fputs($local_db, "\"$vendor_id\"" . "," . "\"$remote_result\"\n");
            fclose($local_db);
            // we need to know if local db was updated to reload the file
            $local_db_updated = true;
            $records_updated++;
        }

        $vendor_name = "(RDB) " . $remote_result;
    } else {
        $vendor_name = "(LDB) " . $local_result;
    }
    // print vendor name
    echo "$mac_address : $vendor_name \n";

    // Print informational message about new records inserted into local database
    if ($records_updated > 0) {
        echo "\nLocal DB was updated with: $records_updated Vendor ID/Name\n";
    }
}



// Help Function
function print_help_message()
{
    print_r("Usage:\n");
    print_r("\t-f <file_name> fo bulk search. Each MAC Address per new line.\n");
    print_r("\t-m <mac_address> fo single MAC Address search.\n");
    print_r("\t-h Display this help message\n");
    print_r("Do not use -m and -f in the same time!\n");
    print_r("Default Local DB file name: \"local_vendor_db.txt\" in " . realpath(dirname(__FILE__)) . "\n");
}
