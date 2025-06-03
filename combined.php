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
      <link rel="stylesheet" type="text/css" href="css/bootstrap.min.css">
      <!-- Style CSS -->
      <link rel="stylesheet" type="text/css" href="css/stylegrades.css">
      <link rel="stylesheet" href="css/style.css">
      <!-- Responsive CSS -->
      <link rel="stylesheet" href="css/responsive.css">
      <!-- Favicon -->
      <link rel="icon" href="images/fevicon.png" type="image/gif">
      <!-- Font CSS -->
      <link href="https://fonts.googleapis.com/css?family=Baloo+Chettan+2:400,600,700|Poppins:400,600,700&display=swap" rel="stylesheet">
      <!-- Scrollbar Custom CSS -->
      <link rel="stylesheet" href="css/jquery.mCustomScrollbar.min.css">
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

                
<!--
 <div class="col-md-4">
    <div class="banner_taital_main">
        <a href="https://www.gradeinsight.com/breaks.html" class="search_bt">See the Breaks</a>
    </div>
</div>

-->

<!-- copyright section start -->
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


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Outhouse</title>
    <script src="https://cdn.jsdelivr.net/npm/fuse.js@6.4.6/dist/fuse.min.js"></script>

  <!-- External Stylesheets -->
   <link rel="stylesheet" href="css/bootstrap.min.css">
   <link rel="stylesheet" href="css/style.css">
   
   <link rel="stylesheet" href="css/responsive.css">
   <link rel="stylesheet" href="css/jquery.mCustomScrollbar.min.css">
   <link href="https://fonts.googleapis.com/css?family=Baloo+Chettan+2:400,600,700|Poppins:400,600,700&display=swap" rel="stylesheet">

</head>
<body>
   
   <!-- Row 1: Title and Image -->
  <div class="container">
   <!-- Row 1: Title and Image -->
   <div class="row">
      <div class="col-md-8">
         <h1 class="banner_taital">Project Outhouse</h1>
      </div>
      <div class="col-md-3">
         <img src="images/grade.png" alt="Grade Image" class="banner_img">
      </div>
   </div>

   <!-- Row 2: Name and Homeroom Selection -->
   <div class="row" style="margin-top: 1rem;">
      <div class="col-md-12">
         <input type="text" id="searchName" placeholder="Search by Name" class="search_text">
      </div>
   </div>

  
   

   <!-- Row 2: Name and Homeroom Selection -->
   <div class="row">
      
   </div>
   
   <!-- Row 3: Homeroom Header and Radio Buttons -->
   <div class="row"">
      <div class="col-md-12">
         <h3>Choose your Homeroom</h3>  <!-- Added header here -->
         <div style="font-size: 1.2em; font-style: bold; display: flex; justify-content: space-around;">
            <label><input type="radio" name="homeroom" value="7A" style="transform: scale(1.5);"> 7A</label>
            <label><input type="radio" name="homeroom" value="7B" style="transform: scale(1.5);"> 7B</label>
            <label><input type="radio" name="homeroom" value="7C" style="transform: scale(1.5);"> 7C</label>
            <label><input type="radio" name="homeroom" value="7D" style="transform: scale(1.5);"> 7D</label>
            <label><input type="radio" name="homeroom" value="7E" style="transform: scale(1.5);"> 7E</label>
            <label><input type="radio" name="homeroom" value="7F" style="transform: scale(1.5);"> 7F</label>
         </div>
      </div>
   </div>

   <!-- Row 4: Button -->
   <div class="row" style="margin-top: 1rem;">
      <div class="col text-center">
         <button onclick="fetchData()" class="search_bt">See Your Breaks</button>
      </div>
   </div>


<div id="results" style="margin-top: 1rem; margin-bottom: 1rem;"></div>
</div>
    <script>
        const apiKey = 'AIzaSyBaHjs64DGiaLwms_YlmaSdxD9MNP43jPc'; // Replace with your API key
        const sheetId = '1phAIVtUMagoOF37_9TzfS-HAejpVeT3cdTjg0NYj_og'; // Replace with your sheet ID
        const range = '2024-2025 Room 313 Sign Out!A:C'; // Updated range to include homeroom

        function fetchData() {
            const nameQuery = document.getElementById('searchName').value.toLowerCase();

            // Get selected homeroom from radio buttons
            const homeroomQuery = document.querySelector('input[name="homeroom"]:checked')?.value || '';

            const url = `https://sheets.googleapis.com/v4/spreadsheets/${sheetId}/values/${range}?key=${apiKey}`;

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    const rows = data.values || [];
                    const resultsDiv = document.getElementById('results');
                    resultsDiv.innerHTML = ''; // Clear previous results

                    if (rows.length > 1) { // Skip header row
                        const namesArray = rows.slice(1).map(row => ({
                            timestamp: formatDate(row[0]),
                            name: row[1],
                            homeroom: row[2]
                        }));
                        
                        const fuse = new Fuse(namesArray, {
                            keys: ['name'],
                            threshold: 0.2,
                            distance: 100 
                        });

                        const results = fuse.search(nameQuery);
                        const filteredResults = results.filter(result =>
                            homeroomQuery === '' || result.item.homeroom === homeroomQuery
                        );

                        if (filteredResults.length > 0) {
                            let tableHTML = `
                                <table class="result-table-grade">
                                    <tr>
                                        <th>Timestamp</th>
                                        <th>Name</th>
                                        <th>Homeroom</th>
                                    </tr>`;
                            
                            filteredResults.forEach(result => {
                                tableHTML += `
                                    <tr>
                                        <td>${result.item.timestamp}</td>
                                        <td>${result.item.name}</td>
                                        <td>${result.item.homeroom}</td>
                                    </tr>`;
                            });
                            tableHTML += `</table>`;
                            resultsDiv.innerHTML = tableHTML;
                        } else {
                            resultsDiv.innerHTML = '<p>No data found for this name in the selected homeroom.</p>';
                        }
                    } else {
                        resultsDiv.innerHTML = '<p>No data found in the sheet.</p>';
                    }
                })
                .catch(error => {
                    console.error('Error fetching data:', error);
                    resultsDiv.innerHTML = '<p>Error fetching data. Please try again later.</p>';
                });
        }

        // Format the timestamp into a more readable date format
        function formatDate(timestamp) {
            const date = new Date(timestamp);
            return date.toLocaleString('en-US', {
                month: 'long', day: 'numeric', year: 'numeric',
                hour: 'numeric', minute: 'numeric', hour12: true
            });
        }
    </script>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Percentage</title>
    <script src="https://cdn.jsdelivr.net/npm/fuse.js@6.4.6/dist/fuse.min.js"></script>

    <!-- External Stylesheets -->
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/responsive.css">
    <link rel="stylesheet" href="css/jquery.mCustomScrollbar.min.css">
    <link href="https://fonts.googleapis.com/css?family=Baloo+Chettan+2:400,600,700|Poppins:400,600,700&display=swap" rel="stylesheet">
</head>
<body>

    <!-- Row 1: Title and Image -->
    <div class="container">
        <div class="row">
            <div class="col-md-8">
                <h1 class="banner_taital">Project Percentage</h1>
            </div>
            <div class="col-md-3">
                <img src="images/grade.png" alt="Grade Image" class="banner_img">
            </div>
        </div>

        <!-- Row 2: Student Number Input -->
        <div class="row" style="margin-top: 1rem;">
            <div class="col-md-12">
                <input type="text" id="studentNumber" placeholder="Enter Student Number" class="search_text">
            </div>
        </div>

        <!-- Row 3: Button to Search Marks -->
        <div class="row" style="margin-top: 1rem;">
            <div class="col text-center">
                <button onclick="fetchStudentMark()" class="search_bt">Get Mark</button>
            </div>
        </div>

        <!-- Row 4: Display Results -->
        <div id="results" style="margin-top: 1rem; margin-bottom: 1rem;"></div>

    </div>

    <script>
        const apiKey = 'AIzaSyBaHjs64DGiaLwms_YlmaSdxD9MNP43jPc'; // Replace with your API key
        const sheetId = '1uQstsXok6L1pI_dyMYBxJo4Ilo4I66Nz5U6W1Z3vjL8'; // Replace with your sheet ID
        const range = 'A3:AG'; // The data range (Student Numbers in Column F and Marks in Column AG)

        function fetchStudentMark() {
            const studentNumber = document.getElementById('studentNumber').value.trim();
            const resultsDiv = document.getElementById('results');
            resultsDiv.innerHTML = ''; // Clear previous results

            if (!studentNumber) {
                resultsDiv.innerHTML = '<p>Please enter a student number.</p>';
                return;
            }

            const url = `https://sheets.googleapis.com/v4/spreadsheets/${sheetId}/values/${range}?key=${apiKey}`;

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    const rows = data.values || [];

                    if (rows.length > 0) {
                        let markFound = false;

                        // Loop through the rows to find the matching student number in Column F (index 5)
                        for (let i = 0; i < rows.length; i++) {
                            if (rows[i][5] === studentNumber) { // Check for matching student number in Column F
                                // Fetch name from columns B and C (Last Name and First Name), and the mark from column AG (index 32)
                                const lastName = rows[i][1];  // Column B: Last Name (Index 1)
                                const firstName = rows[i][2]; // Column C: First Name (Index 2)
                                const mark = rows[i][32];     // Column AG: Mark (Index 32)

                                // Create a table to display the corresponding name and mark
                                resultsDiv.innerHTML = `
                                    <table style="width: 100%; margin-top: 1rem; border: 2px solid #333; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); font-size: 1.5rem; border-collapse: collapse;">
                                        <tr style="background-color: #f4f4f4;">
                                            <th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Name</th>
                                            <th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Mark</th>
                                        </tr>
                                        <tr>
                                            <td style="padding: 10px; text-align: left; border: 1px solid #ddd;">${lastName}, ${firstName}</td>
                                            <td style="padding: 10px; text-align: left; border: 1px solid #ddd;">${mark}</td>
                                        </tr>
                                    </table>`;
                                markFound = true;
                                break;
                            }
                        }

                        if (!markFound) {
                            resultsDiv.innerHTML = '<p>Student number not found.</p>';
                        }
                    } else {
                        resultsDiv.innerHTML = '<p>No data found in the sheet.</p>';
                    }
                })
                .catch(error => {
                    console.error('Error fetching data:', error);
                    resultsDiv.innerHTML = '<p>Error fetching data. Please try again later.</p>';
                });
        }
    </script>

</body>
</html>