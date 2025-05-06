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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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

        /* Styles for the sort menu */
        #sort-menu {
            display: none;
            position: absolute;
            background-color: white;
            border: 1px solid #cccccc;
            border-radius: 6px;
            padding: 8px;
            top: 30px;
            right: 0px;
        }

        #sort-menu a {
            display: block;
            padding: 8px;
            color: black;
            text-decoration: none;
        }

        #sort-menu a:hover {
            background-color: #f2f2f2;
        }

        #hamburger-icon {
            font-size: 24px;
            cursor: pointer;
        }

        .hamburger-menu {
            position: relative;
        }

        .hamburger-menu.open #sort-menu {
            display: block;
        }
    </style>
</head>
<body>
<?php if (!empty($publications)): ?>
    <div style="background-color: #f26522; color: white; padding: 16px; display: flex; justify-content: space-between; align-items: center; border-radius: 6px">
        <div style="font-size: 20px; font-weight: bold;">Works (<?php echo count($publications); ?>)</div>
        <div class="hamburger-menu">
            <i id="hamburger-icon" class="fas fa-bars"></i>
            <div id="sort-menu">
                <a href="#" onclick="sortPublications('date')">Date</a>
                <a href="#" onclick="sortPublications('type')">Type</a>
            </div>
        </div>
    </div>
    <div id="publication-container">
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
    </div>

<?php else: ?>
    <p>No publications found or there was an error with the API request.</p>
<?php endif; ?>

<script>
    const publications = <?php echo json_encode($publications); ?>;
    const container = document.getElementById('publication-container');
    
    // Toggle hamburger menu
    document.getElementById('hamburger-icon').addEventListener('click', function() {
        document.querySelector('.hamburger-menu').classList.toggle('open');
    });

    function getDocumentTypeFull(pub) {
        const type = pub['subtypeDescription'] || '';
        const aggType = pub['prism:aggregationType'] || '';
        if (aggType === 'Journal' && type === 'Article') return 'Journal article';
        if (aggType === 'Conference Proceeding' && type === 'Conference Paper') return 'Conference paper';
        if (aggType === 'Book') return 'Book chapter';
        return type;
    }

    function renderCards(data) {
        container.innerHTML = '';
        data.forEach(pub => {
            const type = getDocumentTypeFull(pub);
            const year = pub['prism:coverDate'] ? pub['prism:coverDate'].substring(0, 4) : 'N/A';
            const doi = pub['prism:doi'] || '';
            const eid = pub['eid'] || '';
            const title = pub['dc:title'] || '';
            const publicationName = pub['prism:publicationName'] || '';
            const contributors = pub['dc:creator'] || 'No contributors found';

            const doiHTML = doi ? `<p>DOI: <a href="https://doi.org/${doi}" target="_blank">${doi}</a></p>` : '';
            const contributorHTML = `<p style="color: red;">CONTRIBUTORS: ${contributors}</p>`;

            const html = `
                <div class="card">
                    <div class="card-header"><b><div>${title}</div></b></div>
                    <div class="card-content">
                        <p>${publicationName}</p>
                        <p>${year} | ${type}</p>
                        ${doiHTML}
                        <p>EID: ${eid}</p>
                        ${contributorHTML}
                    </div>
                    <div class="card-footer">
                        <strong style="color: black;">Source:</strong>
                        <img src="https://orcid.org/assets/vectors/profile-not-verified.svg"
                             alt="ORCID Icon" style="width: 20px; height: 20px; margin: 0 4px; vertical-align: middle;">
                        Komsan Srivisut via Scopus - Elsevier
                    </div>
                </div>`;
            container.innerHTML += html;
        });
    }

    function sortPublications(sortBy) {
        const sorted = [...publications];

        if (sortBy === 'date') {
            sorted.sort((a, b) => new Date(b['prism:coverDate']) - new Date(a['prism:coverDate']));
        } else if (sortBy === 'type') {
            sorted.sort((a, b) => {
                const typeA = getDocumentTypeFull(a).toLowerCase();
                const typeB = getDocumentTypeFull(b).toLowerCase();
                return typeA.localeCompare(typeB);
            });
        }

        renderCards(sorted);
    }
</script>

</body>
</html>
