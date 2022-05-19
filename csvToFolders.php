<?php

/**
 * Μαζική δημιουργία φακέλων σύμφωνα με τα δεδομένα ενός αρχείου csv.
 * php version 7.4
 * 
 * @category Application
 * @package  CsvToFolders
 * @author   Theofilos Intzoglou <int.teo@gmail.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 * @link     https://github.com/DSE-West-Thessaloniki/csvToFolder
 */

$rest_index = null;
$options = getopt("c:d:ho:", ["column:", "delimiter:", "header", "output"], $rest_index);
$remaining_args = array_slice($argv, $rest_index);
if (!$options || !$remaining_args || count($remaining_args) > 1) {
    echo "Usage: cvsToFolders [Επιλογές] <filename.csv>\n";
    echo "Επιλογές:\n";
    echo " -d,--delimiter 'διαχωριστικό' - Ο χαρακτήρας που χρησιμοποιείται σαν διαχωριστικό στο αρχείο".PHP_EOL;
    echo " -c,--column 'αριθμός στήλης'  - Ο αριθμός της στήλης που θα χρησιμοποιηθεί ως δεδομένο για επίπεδο φακέλων".PHP_EOL.PHP_EOL;
    echo " -h,--header                   - Το csv αρχείο έχει επικεφαλίδες στην πρώτη γραμμή".PHP_EOL;
    echo " -o,--output                   - Ο προορισμός στον οποίο θα δημιουργηθούν οι φάκελοι".PHP_EOL;
    echo "Παρατηρήσεις: Η προεπιλεγμένη τιμή για το διαχωριστικό είναι ο χαρακτήρας ','. Η επιλογή -c μπορεί να".PHP_EOL;
    echo "χρησιμοποιηθεί πολλαπλές φορές αν θέλουμε να έχουμε πολλαπλά επίπεδα φακέλων. Η αρίθμηση των στηλών αρχίζει από το 1.".PHP_EOL.PHP_EOL;
    echo "Παράδειγμα: cvsToFolders -d ',' -c3 -c1 -c5 test.csv".PHP_EOL;
    exit(0);
}

$columns = array_filter(
    $options, 
    fn($option) => $option === 'c' || $option === 'column',
    ARRAY_FILTER_USE_KEY
);

if ($columns) {
    $flattened = [];
    foreach ($columns as $key) {
        if (is_array($key)) {
            array_push($flattened, ...$key);
        } else {
            array_push($flattened, $key);
        }
    } 
}
$columns = $flattened;

$columns = array_map(fn($column) => intval($column), $columns);

foreach ($columns as $column) {
    if ($column === 0) {
        echo "Η αρίθμηση των στηλών αρχίζει από το 1. Ελέγξτε ότι έχετε δώσει σωστά ορίσματα.".PHP_EOL;
        exit(2);
    }
}

$delimiter = ',';
if (isset($options['d'])) {
    $delimiter = $options['d'];
}
if (isset($options['delimiter'])) {
    $delimiter = $options['delimiter'];
}

$header = isset($options['h']) || isset($options['header']) ? true : false;

$output = '.';
if (isset($options['o'])) {
    $output = $options['o'];
}
if (isset($options['output'])) {
    $output = $options['output'];
}

$filename = realpath($remaining_args[0]);

if (!$filename) {
    echo "Το αρχείο ".$remaining_args[0]." δεν υπάρχει ή δεν είναι τοπικό αρχείο.".PHP_EOL;
    exit(1);
}

if (!is_file($filename)) {
    echo "Το ".$filename." δεν είναι αρχείο.".PHP_EOL;
    exit(1);
}

$skip = 0;
if ($header) {
    $skip = 1;
}
$headers = [];

if (!$skip) {
    echo "Γίνεται χρήση των στηλών: ";
    foreach ($columns as $key => $column) {
        echo "'".$column."'";
        if ($key != (count($columns) - 1)) {
            echo ",";
        } else {
            echo PHP_EOL;
        }
    }
}

$dir_names = [];
$line_number = 1;
if (($handle = fopen($filename, "r")) !== false) {
    while (($data = fgetcsv($handle, 10000, $delimiter)) !== false) {
        if (!$skip) { // Αν δεν είμαστε σε γραμμή επικεφαλίδων
            $tmp = [];
            foreach ($columns as $column) {
                if (!isset($data[$column - 1])) {
                    echo "Σφάλμα στην γραμμή ".$line_number.". Δεν βρέθηκε η στήλη ".($column - 1).".".PHP_EOL;
                    exit(2);
                }
                array_push($tmp, $data[$column - 1]);
            }
            array_push($dir_names, $tmp);
        } else { // Το αρχείο έχει επικεφαλίδες
            foreach ($columns as $column) {
                if (!isset($data[$column - 1])) {
                    echo "Λάθος αριθμός στήλης (".($column - 1).")".PHP_EOL;
                    exit(2);
                }
                array_push($headers, $data[$column - 1]);
            }
            echo "Γίνεται χρήση των στηλών: ";
            foreach ($headers as $key => $header) {
                echo "'".$header."'";
                if ($key != (count($headers) - 1)) {
                    echo ",";
                } else {
                    echo PHP_EOL;
                }
            }
            $skip--;
        }
        $line_number++;
    }
    fclose($handle);
    foreach ($dir_names as $path) {
        $new_dir = $output;
        foreach ($path as $dir) {
            $new_dir .= "/$dir";
            if (file_exists($new_dir) && !is_dir($new_dir)) {
                echo "Η διαδρομή ".$new_dir." καταλήγει σε αρχείο! Γίνεται τερματισμός...".PHP_EOL;
                exit(3);
            }
            if (!file_exists($new_dir)) {
                if (!mkdir($new_dir)) {
                    echo "Σφάλμα κατά την δημιουργία του φακέλου ".$new_dir."! Γίνεται τερματισμός...".PHP_EOL;
                    exit(4);
                }
            }
        }
    }
} else {
    echo "Σφάλμα κατά το άνοιγμα του αρχείο για ανάγνωση!".PHP_EOL;
    exit(1);
}