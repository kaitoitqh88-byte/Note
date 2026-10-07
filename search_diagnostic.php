<?php
/**
 * Search Diagnostic Tool
 * Check if all required files and functions are available
 */

echo "<h2>Search Diagnostic Report</h2>";

// 1. Check required files
echo "<h3>1. File Check</h3>";
$requiredFiles = [
    'config.php' => 'Configuration file',
    'CloudflareAPI.php' => 'Cloudflare API class',
    'SearchHandler.php' => 'Search handler functions',  
    'CloudflareCache.php' => 'Cache management',
    'search.php' => 'Search interface',
    'token.txt' => 'API Token file'
];

foreach ($requiredFiles as $file => $description) {
    if (file_exists($file)) {
        echo "<div style='color: green'>✓ {$file} - {$description}</div>";
    } else {
        echo "<div style='color: red'>✗ {$file} - {$description} [MISSING]</div>";
    }
}

// 2. Check includes
echo "<h3>2. Include Test</h3>";
try {
    echo "<div>Including config.php... ";
    require_once 'config.php';
    echo "<span style='color: green'>OK</span></div>";
    
    echo "<div>Including CloudflareAPI.php... ";
    require_once 'CloudflareAPI.php';
    echo "<span style='color: green'>OK</span></div>";
    
    echo "<div>Including SearchHandler.php... ";
    require_once 'SearchHandler.php';
    echo "<span style='color: green'>OK</span></div>";
    
} catch (Exception $e) {
    echo "<span style='color: red'>ERROR: {$e->getMessage()}</span></div>";
}

// 3. Check functions
echo "<h3>3. Function Check</h3>";
$requiredFunctions = [
    'handleSearch' => 'Main search handler',
    'searchInLocalCache' => 'Cache search function',
    'saveSearchToCache' => 'Cache save function'
];

foreach ($requiredFunctions as $func => $description) {
    if (function_exists($func)) {
        echo "<div style='color: green'>✓ {$func}() - {$description}</div>";
    } else {
        echo "<div style='color: red'>✗ {$func}() - {$description} [NOT FOUND]</div>";
    }
}

// 4. Check class and methods
echo "<h3>4. Class & Method Check</h3>";
if (class_exists('CloudflareAPI')) {
    echo "<div style='color: green'>✓ CloudflareAPI class exists</div>";
    
    $cloudflareAPI = new CloudflareAPI();
    $requiredMethods = ['searchZones', 'listZones', 'getAPIStats'];
    
    foreach ($requiredMethods as $method) {
        if (method_exists($cloudflareAPI, $method)) {
            echo "<div style='color: green'>✓ CloudflareAPI::{$method}() method</div>";
        } else {
            echo "<div style='color: red'>✗ CloudflareAPI::{$method}() method [NOT FOUND]</div>";
        }
    }
} else {
    echo "<div style='color: red'>✗ CloudflareAPI class [NOT FOUND]</div>";
}

// 5. Check constants
echo "<h3>5. Configuration Check</h3>";
$requiredConstants = [
    'CLOUDFLARE_API_URL' => 'API URL',
    'CLOUDFLARE_EMAIL' => 'API Email', 
    'CLOUDFLARE_API_TOKEN' => 'API Token'
];

foreach ($requiredConstants as $const => $description) {
    if (defined($const)) {
        $value = constant($const);
        if (!empty($value)) {
            echo "<div style='color: green'>✓ {$const} - {$description} (length: " . strlen($value) . ")</div>";
        } else {
            echo "<div style='color: orange'>⚠ {$const} - {$description} [EMPTY]</div>";
        }
    } else {
        echo "<div style='color: red'>✗ {$const} - {$description} [NOT DEFINED]</div>";
    }
}

// 6. Do a quick search test
echo "<h3>6. Quick API Test</h3>";
try {
    echo "<div>Creating CloudflareAPI instance... ";
    $cloudflare = new CloudflareAPI();
    echo "<span style='color: green'>OK</span></div>";
    
    echo "<div>Testing API connectivity... ";
    $zones = $cloudflare->listZones(1, 3);
    if (isset($zones['result'])) {
        echo "<span style='color: green'>OK - Found " . count($zones['result']) . " zones</span></div>";
        
        echo "<div>Testing search method... ";
        $searchResult = $cloudflare->searchZones('', 1, 2);
        if (isset($searchResult['result'])) {
            echo "<span style='color: green'>OK - Search returned " . count($searchResult['result']) . " results</span></div>";
        } else {
            echo "<span style='color: red'>ERROR - Search returned no results array</span></div>";
        }
    } else {
        echo "<span style='color: red'>ERROR - No zones result</span></div>";
    }
    
} catch (Exception $e) {
    echo "<span style='color: red'>ERROR: {$e->getMessage()}</span></div>";
}

// 7. URL Test
echo "<h3>7. Suggested Test URLs</h3>";
echo "<div><a href='?action=search&api=1&q=&page=1&per_page=5' target='_blank'>Test Basic Search API</a></div>";
echo "<div><a href='?action=search' target='_blank'>Test Search HTML Interface</a></div>";
echo "<div><a href='test_search_api.html' target='_blank'>Open Interactive Test Page</a></div>";

echo "<h3>Diagnostic Complete</h3>";
echo "<p>If there are any red ✗ markers above, those need to be fixed first.</p>";
?>