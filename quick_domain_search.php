<?php
/**
 * Quick Domain Search Script
 */

require_once 'd:\Note\config.php';
require_once 'd:\Note\CloudflareAPI.php';

function searchDomainsByKeyword($api, $keyword) {
    $allZones = $api->listZones(1, 100);
    $matchingDomains = [];
    
    if (!$allZones['success'] || !isset($allZones['result'])) {
        return $matchingDomains;
    }
    
    $keyword = strtolower($keyword);
    
    foreach ($allZones['result'] as $zone) {
        $domainName = strtolower($zone['name']);
        
        if ($domainName === $keyword) {
            $matchingDomains[] = [
                'domain' => $zone['name'],
                'match_type' => 'exact',
                'similarity' => 100
            ];
        } elseif (strpos($domainName, $keyword) !== false) {
            $matchingDomains[] = [
                'domain' => $zone['name'],
                'match_type' => 'contains',
                'similarity' => 90
            ];
        } else {
            $similarity = 0;
            similar_text($keyword, $domainName, $similarity);
            if ($similarity > 60) {
                $matchingDomains[] = [
                    'domain' => $zone['name'],
                    'match_type' => 'similar',
                    'similarity' => round($similarity)
                ];
            }
        }
    }
    
    usort($matchingDomains, function($a, $b) {
        return $b['similarity'] - $a['similarity'];
    });
    
    return array_slice($matchingDomains, 0, 10);
}

try {
    $keyword = 'mb66';
    echo "Searching for domains with keyword: $keyword\n";
    echo "=====================================\n";
    
    $api = new CloudflareAPI();
    $results = searchDomainsByKeyword($api, $keyword);
    
    if (empty($results)) {
        echo "No domains found matching '$keyword'\n";
    } else {
        echo "Found " . count($results) . " matches:\n\n";
        foreach ($results as $result) {
            echo "- " . $result['domain'] . " (" . $result['match_type'] . ", " . $result['similarity'] . "% match)\n";
        }
    }
    
    // Now try other gambling-related keywords
    $keywords = ['bet', 'win', '88', '99'];
    foreach ($keywords as $kw) {
        echo "\n\nSearching for '$kw':\n";
        echo "=================\n";
        $kwResults = searchDomainsByKeyword($api, $kw);
        if (!empty($kwResults)) {
            foreach (array_slice($kwResults, 0, 5) as $result) {
                echo "- " . $result['domain'] . " (" . $result['match_type'] . ", " . $result['similarity'] . "% match)\n";
            }
        } else {
            echo "No matches found for '$kw'\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>