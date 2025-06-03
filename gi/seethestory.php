<!DOCTYPE html>
<html>
   <head>
      <!-- Basic -->
      <meta charset="utf-8">
      <meta http-equiv="X-UA-Compatible" content="IE=edge">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <meta name="viewport" content="initial-scale=1, maximum-scale=1">

      <!-- Site Metas -->
      <title>Grade Insight</title>
      <meta name="keywords" content="">
      <meta name="description" content="">
      <meta name="author" content="">

      <!-- Bootstrap CSS -->
      <link rel="stylesheet" type="text/css" href="/css/bootstrap.min.css">
      <!-- Style CSS -->
      <link rel="stylesheet" type="text/css" href="/css/stylegrades.css">
      <link rel="stylesheet" href="css/style.css">
      <!-- Responsive CSS -->
      <link rel="stylesheet" href="/css/responsive.css">
      <!-- Favicon -->
      <link rel="icon" href="/images/fevicon.png" type="image/gif">
      <!-- Font CSS -->
      <link href="https://fonts.googleapis.com/css?family=Baloo+Chettan+2:400,600,700|Poppins:400,600,700&display=swap" rel="stylesheet">
      <!-- Scrollbar Custom CSS -->
      <link rel="stylesheet" href="/css/jquery.mCustomScrollbar.min.css">
      <!-- Tweaks for older IEs -->
      <link rel="stylesheet" href="https://netdna.bootstrapcdn.com/font-awesome/4.0.3/css/font-awesome.css">
   </head>
   <body>
    

<?php

// Include the configuration file
$config = include('config.php');

// Retrieve values from the configuration
$apiKey = $config['apiKey'];
$spreadsheetIds = $config['spreadsheetIds'];
$cacheDir = __DIR__ . '/cache/'; // Path to the cache directory

// Include the required dependencies
require 'path_to/google-api-php-client/vendor/autoload.php';

// Function to authenticate with Google Sheets API
function authenticateGoogleSheetsAPI($apiKey)
{
    $client = new Google_Client();
    $client->setApplicationName('Web Client 1');
    $client->setDeveloperKey($apiKey); // Set the API key

    return new Google_Service_Sheets($client);
}

// Function to fetch data from Google Sheets based on the search term, sheet name, and dynamic range
function getGoogleSheetsDataBySearchTermAndSheet($spreadsheetId, $sheetName, $apiKey, $searchTerm, $cacheDir)
{
    // Generate a unique cache key based on the sheet, search term, and API key
    $cacheKey = md5($spreadsheetId . $sheetName . $searchTerm . $apiKey);

    // Check if the data is already cached
    $cacheFile = $cacheDir . $cacheKey . '.json';
    if (file_exists($cacheFile) && time() - filemtime($cacheFile) < 60 * 5) { // Cache for 5 minutes
        // Return cached data
        return json_decode(file_get_contents($cacheFile), true);
    }

    $service = authenticateGoogleSheetsAPI($apiKey);

    // Fetch data from Google Sheets
    $spreadsheet = $service->spreadsheets->get($spreadsheetId);
    $sheets = $spreadsheet->getSheets();

    // Find the sheet with the specified name
    $sheetId = null;
    foreach ($sheets as $sheet) {
        if ($sheet->getProperties()->getTitle() == $sheetName) {
            $sheetId = $sheet->getProperties()->getSheetId();
            break;
        }
    }

  if ($sheetId !== null) {
        // Fetch data from the specified sheet
        $range = $sheetName; // Fetch the entire sheet
        $response = $service->spreadsheets_values->get($spreadsheetId, $range);
        $allData = $response->getValues();

        // Find the last column with data
        $endColumn = 'A';
        foreach ($allData[0] as $column => $value) {
            if (!empty($value)) {
                $endColumn = chr(ord('A') + $column);
            }
        }

        // Filter data based on the search term
        $filteredData = array_filter($allData, function ($row) use ($searchTerm) {
            return $row[4] == $searchTerm || $row[5] == $searchTerm;
        });

        // Save data to cache
        $cacheData = array('allData' => $allData, 'filteredData' => $filteredData, 'endColumn' => $endColumn);
        file_put_contents($cacheFile, json_encode($cacheData));

        return $cacheData;
    } else {
        return array('allData' => array(), 'filteredData' => array(), 'endColumn' => 'Z');
    }
}

// Function to fetch all sheets from a spreadsheet
function getAllSheets($spreadsheetId, $apiKey)
{
    $service = authenticateGoogleSheetsAPI($apiKey);
    $spreadsheet = $service->spreadsheets->get($spreadsheetId);
    $sheets = $spreadsheet->getSheets();

    return $sheets;
}

// Function to check if a sheet contains the search term in its data
function sheetContainsSearchTerm($sheet, $spreadsheetId, $apiKey, $searchTerm, $cacheDir)
{
    $sheetName = $sheet->getProperties()->getTitle();
    $googleSheetsData = getGoogleSheetsDataBySearchTermAndSheet($spreadsheetId, $sheetName, $apiKey, $searchTerm, $cacheDir);

    return !empty($googleSheetsData['filteredData']);
}

// Handle the form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $searchTerm = filter_input(INPUT_POST, 'searchTerm', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    // Validate the search term
    if ($searchTerm === false) {
        // Handle validation error (e.g., show an error message)
        echo "Invalid search term.";
        exit;
    }

    // Display the first table flag
    $displayFirstTable = true;

    // Process and display data for each spreadsheet
    foreach ($spreadsheetIds as $spreadsheetId) {
        $service = authenticateGoogleSheetsAPI($apiKey);
        $sheets = getAllSheets($spreadsheetId, $apiKey);

        // Display data for each sheet in the current spreadsheet
        foreach ($sheets as $sheet) {
            // Check if the sheet contains the search term
            if (sheetContainsSearchTerm($sheet, $spreadsheetId, $apiKey, $searchTerm, $cacheDir)) {
                $sheetName = $sheet->getProperties()->getTitle();
                $googleSheetsData = getGoogleSheetsDataBySearchTermAndSheet($spreadsheetId, $sheetName, $apiKey, $searchTerm, $cacheDir);

                // Display data for the current sheet
                if (!empty($googleSheetsData['filteredData'])) {
                    // Assuming you only want to display the first matching row
                    $row = reset($googleSheetsData['filteredData']);

                    // Display the first table only once at the top
                    if ($displayFirstTable) {
                        ?>
<div class="row">
    <div class="col-md-12">
        <div class="banner_taital_main">
       
                        <table class="result-table">
                            <thead>
                                <th>ID</th>
                                <th>LAST NAME</th>
                                <th>FIRST NAME</th>
                                <th>CLASS</th>
                                <th>GENERIC NUMBER</th>
                                <th>STUDENT NUMBER</th>
                            </thead>

                            <tbody>
                                <tr>
                                    <td><?php echo isset($row[0]) ? htmlspecialchars($row[0], ENT_QUOTES, 'UTF-8') : ''; ?></td>
                                    <td><?php echo isset($row[1]) ? htmlspecialchars($row[1], ENT_QUOTES, 'UTF-8') : ''; ?></td>
                                    <td><?php echo isset($row[2]) ? htmlspecialchars($row[2], ENT_QUOTES, 'UTF-8') : ''; ?></td>
                                    <td><?php echo isset($row[3]) ? htmlspecialchars($row[3], ENT_QUOTES, 'UTF-8') : ''; ?></td>
                                    <td><?php echo isset($row[4]) ? htmlspecialchars($row[4], ENT_QUOTES, 'UTF-8') : ''; ?></td>
                                    <td><?php echo isset($row[5]) ? htmlspecialchars($row[5], ENT_QUOTES, 'UTF-8') : ''; ?></td>
                                </tr>
                            </tbody>
                        </table>
   
    </div>
    </div>

                        
                        <?php
                        $displayFirstTable = false; // Set the flag to false after displaying the first table
                    }

                    // Display the second table (grades) for each sheet
                    ?>

    <div class="col-md-4">
        <div class="banner_taital_main">
                    <h2><?php echo $sheetName; ?></h2>
                    <table class="result-table-grade" border="1">
                        <tr>
                            <th>ASSIGNMENT</th>
                            <th>GRADE</th>
                        </tr>

                        <?php
                        // Assuming assignment columns start from index 6
                        $assignmentColumns = array_slice($row, 6);

                        // Fetch assignment names from the first row starting at column G
                        $assignmentNames = array_slice($googleSheetsData['allData'][0], 6);

                        foreach ($assignmentNames as $index => $assignmentName) {
                            echo "
                            <tr>
                                <td>" . (isset($assignmentName) ? htmlspecialchars($assignmentName, ENT_QUOTES, 'UTF-8') : '') . "</td>
                                <td>" . (isset($assignmentColumns[$index]) ? htmlspecialchars($assignmentColumns[$index], ENT_QUOTES, 'UTF-8') : '') . "</td>
                            </tr>";
                        }
                        ?>
                    </table>
</div>
</div>

        

                    <?php
                } else {
                    echo "<p>No records found for the specified search term in $sheetName.</p>";
                }
            }
        }
    }
}
?>


                           </div>
                        </div>
                        
                       
                     </div>
                  </div>


<br>
<div class="copyright_section">
   <div class="container">
      <p class="copyright_text">2024 All Rights Reserved. Design by <a href="https://gradeinsight.com/stolas" target="_blank">Stolas Learning Solutions</a></p>
   </div>
</div>
<!-- copyright section end -->

      <!-- Javascript files-->
      <script src="js/jquery.min.js"></script>
      <script src="js/popper.min.js"></script>
      <script src="js/bootstrap.bundle.min.js"></script>
      <script src="js/jquery-3.0.0.min.js"></script>
      <script src="js/plugin.js"></script>
      <!-- sidebar -->
      <script src="js/jquery.mCustomScrollbar.concat.min.js"></script>
      <script src="js/custom.js"></script>
   </body>
</html>