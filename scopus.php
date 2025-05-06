<?php
$apiKey = "ae7e84e02386105442a7e6d7919f5d4e";
$baseUrl = "https://api.elsevier.com/content/search/scopus";
$authorId = "23096399800";

function fetchPublications($baseUrl, $apiKey, $authorId) {
    $queryParams = http_build_query([
        'query' => "AU-ID($authorId)",
        'apiKey' => $apiKey
    ]);

    $url = $baseUrl . '?' . $queryParams;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($httpCode === 200) {
        $data = json_decode($response, true);

        // แสดงข้อมูลที่ได้รับจาก API สำหรับการดีบัก
        // echo "<pre>";
        // print_r($data);
        // echo "</pre>";

        curl_close($ch);
        return $data["search-results"]["entry"] ?? [];
    } else {
        echo "Error: HTTP status code $httpCode\n";
    }

    curl_close($ch);
    return [];
}

$publications = fetchPublications($baseUrl, $apiKey, $authorId);

// echo "<pre>";
// print_r($publications);
// echo "</pre>";

function getDocumentTypeFull($publication) {
    $type = $publication['subtypeDescription'] ?? '';
    $aggType = $publication['prism:aggregationType'] ?? '';

    if ($aggType === 'Journal' && $type === 'Article') {
        return 'Journal article';
    } elseif ($aggType === 'Conference Proceeding' && $type === 'Conference Paper') {
        return 'Conference paper';
    } elseif ($aggType === 'Book') {
        return 'Book chapter';
    }

    return $type;
}

if (empty($publications)) {
    echo "No publications found or there was an error with the API request.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Show Card Works</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <style>
        body {
            font-family: 'Noto Sans', sans-serif;
            font-size: 16px;
        }

        .card {
            background: #ffffff;
            border: 1px solid #cccccc;
            border-radius: 6px;
            width: 100%;
            overflow: hidden;
            margin-top: 20px;
        }

        .card-header {
            background-color: #eee;
            padding: 16px;
            border-bottom: 1px solid #cccccc;
        }

        .card-content {
            padding: 16px;
        }

        .card-content p {
            margin: 4px 0;
        }

        .card-content a {
            color: #085c77;
        }

        .card-content a:hover {
            text-decoration: underline;
        }

        .card-footer {
            background-color: white;
            padding: 8px 16px;
            color: #555;
            border-top: 1px solid #cccccc;
        }
    </style>
</head>
<body>
<?php if (!empty($publications)): ?>
    <?php foreach ($publications as $publication): ?>
        <div class="card">
            <div class="card-header">
                <b>
                    <div><?php echo htmlspecialchars($publication['dc:title']); ?></div>
                </b>
            </div>
            <div class="card-content">
                <p><?php echo htmlspecialchars($publication['prism:publicationName']); ?></p>
                <p>
                    <?php echo substr($publication['prism:coverDate'], 0, 4); ?> |
                    <?php echo htmlspecialchars(getDocumentTypeFull($publication)); ?>
                </p>

                <p>
                    <?php if (!empty($publication['prism:doi'])): ?>
                        DOI:
                        <a href="https://doi.org/<?php echo htmlspecialchars($publication['prism:doi']); ?>" target="_blank">
                            <?php echo htmlspecialchars($publication['prism:doi']); ?>
                        </a>
                    <?php endif; ?>
                </p>

                <p>EID: <?php echo htmlspecialchars($publication['eid']); ?></p>

                <!-- ISBN Section -->
                <?php if (!empty($publication['prism:isbn'])): ?>
                    <p>Part of ISBN:
                        <?php
                        $isbns = $publication['prism:isbn'];
                        $values = [];

                        if (is_array($isbns)) {
                            foreach ($isbns as $item) {
                                if (is_array($item) && isset($item['$'])) {
                                    if (is_array($item['$'])) {
                                        $values = array_merge($values, $item['$']);
                                    } else {
                                        $split = preg_split('/[\s,]+/', $item['$']);
                                        $values = array_merge($values, $split);
                                    }
                                } else {
                                    $values[] = $item;
                                }
                            }
                        } else {
                            $values[] = $isbns;
                        }

                        $cleaned_values = array_map(function($isbn) {
                            return preg_replace('/[^\d]/', '', $isbn);
                        }, $values);

                        $isbn_links = array_map(function($isbn) {
                            return '<a href="https://search.worldcat.org/th/search?q=bn:' . $isbn . '" target="_blank">' . $isbn . '</a>';
                        }, $cleaned_values);

                        echo implode(' ', $isbn_links);
                        ?>
                    </p>
                <?php endif; ?>

                <!-- Part of ISSN Section -->
                <?php if (!empty($publication['prism:issn']) || !empty($publication['prism:eIssn'])): ?>
                    <p>Part of ISSN:
                        <?php
                        $issns = [];

                        if (!empty($publication['prism:eIssn'])) {
                            $issns[] = $publication['prism:eIssn'];
                        }
                        if (!empty($publication['prism:issn'])) {
                            $issns[] = $publication['prism:issn'];
                        }

                        $issn_links = array_map(function($issn) {
                            $formatted = preg_replace('/(\d{4})(\d{4})/', '$1-$2', $issn);
                            return '<a href="https://portal.issn.org/resource/ISSN/' . $formatted . '" target="_blank">' . $issn . '</a>';
                        }, $issns);

                        echo implode(' ', $issn_links);
                        ?>
                    </p>
                <?php endif; ?>

                <p style="color: red;">CONTRIBUTORS:
                    <?php
                    if (!empty($publication['dc:creator'])) {
                        echo htmlspecialchars($publication['dc:creator']);
                    } else {
                        echo "No contributors found";
                    }
                    ?>
                </p>
            </div>
            <div class="card-footer">
                <strong style="color: black;">Source:</strong>
                <img src="https://orcid.org/assets/vectors/profile-not-verified.svg"
                     alt="ORCID Icon"
                     style="width: 20px; height: 20px; margin: 0 4px; vertical-align: middle;">
                Komsan Srivisut via Scopus - Elsevier
            </div>
        </div>
    <?php endforeach; ?>
<?php else: ?>
    <p>No publications found or there was an error with the API request.</p>
<?php endif; ?>
</body>
</html>
