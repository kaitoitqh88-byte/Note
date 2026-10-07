<?php
/**
 * Domain Search Tool - Find domains in Cloudflare account by keyword
 */

require_once 'config.php';
require_once 'CloudflareAPI.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        $keyword = $_POST['keyword'] ?? '';
        $api = new CloudflareAPI();
        
        $results = searchDomainsByKeyword($api, $keyword);
        
        echo json_encode([
            'success' => true,
            'search_keyword' => $keyword,
            'results' => $results
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}

function searchDomainsByKeyword($api, $keyword) {
    // Get all zones from Cloudflare account
    $allZones = $api->listZones(1, 100);
    $matchingDomains = [];
    
    if (!$allZones['success'] || !isset($allZones['result'])) {
        return $matchingDomains;
    }
    
    $keyword = strtolower($keyword);
    
    foreach ($allZones['result'] as $zone) {
        $domainName = strtolower($zone['name']);
        
        // Exact match
        if ($domainName === $keyword) {
            $matchingDomains[] = [
                'domain' => $zone['name'],
                'match_type' => 'exact',
                'status' => $zone['status'] ?? 'unknown',
                'plan' => $zone['plan']['name'] ?? 'Unknown',
                'similarity' => 100
            ];
            continue;
        }
        
        // Contains keyword
        if (strpos($domainName, $keyword) !== false) {
            $matchingDomains[] = [
                'domain' => $zone['name'],
                'match_type' => 'contains',
                'status' => $zone['status'] ?? 'unknown',
                'plan' => $zone['plan']['name'] ?? 'Unknown',
                'similarity' => 90
            ];
            continue;
        }
        
        // Similar text (fuzzy matching)
        $similarity = 0;
        similar_text($keyword, $domainName, $similarity);
        if ($similarity > 50) {
            $matchingDomains[] = [
                'domain' => $zone['name'],
                'match_type' => 'similar',
                'status' => $zone['status'] ?? 'unknown',
                'plan' => $zone['plan']['name'] ?? 'Unknown',
                'similarity' => round($similarity)
            ];
        }
    }
    
    // Sort by similarity (highest first)
    usort($matchingDomains, function($a, $b) {
        return $b['similarity'] - $a['similarity'];
    });
    
    return array_slice($matchingDomains, 0, 10); // Return top 10 matches
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Domain Search Tool</title>
    <style>
        body { font-family: 'Roboto', sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .search-box { margin-bottom: 20px; }
        input[type="text"] { width: 300px; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        button { padding: 10px 20px; background: #007cba; color: white; border: none; border-radius: 4px; cursor: pointer; margin-left: 10px; }
        button:hover { background: #005a87; }
        .result-item { background: #f9f9f9; padding: 15px; margin: 10px 0; border-radius: 6px; border-left: 4px solid #007cba; }
        .exact { border-left-color: #28a745; }
        .contains { border-left-color: #17a2b8; }
        .similar { border-left-color: #ffc107; }
        .match-type { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 12px; text-transform: uppercase; }
        .match-exact { background: #d4edda; color: #155724; }
        .match-contains { background: #d1ecf1; color: #0c5460; }
        .match-similar { background: #fff3cd; color: #856404; }
        .similarity { float: right; font-weight: bold; color: #666; }
        .no-results { text-align: center; color: #666; padding: 20px; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Domain Search Tool</h1>
        <p>Search through your Cloudflare domains by keyword</p>
        
        <div class="search-box">
            <input type="text" id="searchKeyword" placeholder="Enter keyword (e.g., mb66, bet, win)" value="mb66">
            <button onclick="searchDomains()">Search Domains</button>
            <button onclick="showAllDomains()">Show All</button>
        </div>
        
        <div id="results"></div>
    </div>
    
    <script>
    function searchDomains() {
        const keyword = document.getElementById('searchKeyword').value.trim();
        if (!keyword) {
            alert('Please enter a keyword to search');
            return;
        }
        
        document.getElementById('results').innerHTML = '<p>Searching...</p>';
        
        fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `keyword=${encodeURIComponent(keyword)}`
        })
        .then(response => response.json())
        .then(data => displayResults(data))
        .catch(error => {
            document.getElementById('results').innerHTML = `<p style="color: red;">Error: ${error}</p>`;
        });
    }
    
    function displayResults(data) {
        const resultsDiv = document.getElementById('results');
        
        if (!data.success) {
            resultsDiv.innerHTML = `<p style="color: red;">Error: ${data.error}</p>`;
            return;
        }
        
        if (data.results.length === 0) {
            resultsDiv.innerHTML = `
                <div class="no-results">
                    <h3>No matches found for "${data.search_keyword}"</h3>
                    <p>Try a different keyword or check the spelling.</p>
                </div>
            `;
            return;
        }
        
        let html = `<h3>Search Results for "${data.search_keyword}" (${data.results.length} matches)</h3>`;
        
        data.results.forEach(result => {
            const matchClass = `match-${result.match_type}`;
            html += `
                <div class="result-item ${result.match_type}">
                    <div style="display: flex; justify-content: between; align-items: center;">
                        <div>
                            <strong>${result.domain}</strong>
                            <span class="match-type ${matchClass}">${result.match_type}</span>
                            <div style="margin-top: 5px; color: #666; font-size: 14px;">
                                Status: ${result.status} | Plan: ${result.plan}
                            </div>
                        </div>
                        <div class="similarity">${result.similarity}% match</div>
                    </div>
                </div>
            `;
        });
        
        resultsDiv.innerHTML = html;
    }
    
    function showAllDomains() {
        document.getElementById('searchKeyword').value = '';
        fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'keyword='
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.results.length > 0) {
                displayResults({
                    success: true,
                    search_keyword: 'all domains',
                    results: data.results.slice(0, 20) // Show first 20
                });
            }
        });
    }
    
    // Auto-search on page load
    window.onload = () => searchDomains();
    
    // Enter key search
    document.getElementById('searchKeyword').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            searchDomains();
        }
    });
    </script>
</body>
</html>