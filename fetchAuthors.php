<?php
$apiKey = "ae7e84e02386105442a7e6d7919f5d4e";
$baseUrl = "https://api.elsevier.com/content/search/scopus";
$baseUrl2 = "https://api.elsevier.com/content/abstract/eid";
$authorId = "23096399800";

// example use baseUrl2 : https://api.elsevier.com/content/abstract/eid/2-s2.0-85216806808?apiKey=ae7e84e02386105442a7e6d7919f5d4e

function fetchPublications($baseUrl, $apiKey, $authorId)
{
    $queryParams = http_build_query([
        "query" => "AU-ID($authorId)",
        "apiKey" => $apiKey,
    ]);

    $url = $baseUrl . "?" . $queryParams;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($httpCode === 200) {
        $data = json_decode($response, true);
        curl_close($ch);
        return $data["search-results"]["entry"] ?? [];
    } else {
        echo "Error: HTTP status code $httpCode\n";
    }

    curl_close($ch);
    return [];
}

// New function to fetch author details using EID
function fetchAuthors($baseUrl2, $apiKey, $eid)
{
    $url = $baseUrl2 . "/" . $eid . "?apiKey=" . $apiKey;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        curl_close($ch);
        
        // Extract authors information from the response
        if (isset($data['abstracts-retrieval-response']['authors']['author'])) {
            return $data['abstracts-retrieval-response']['authors']['author'];
        }
    } else {
        echo "Error fetching authors: HTTP status code $httpCode\n";
    }
    
    curl_close($ch);
    return [];
}

$publications = fetchPublications($baseUrl, $apiKey, $authorId);

function getDocumentTypeFull($publication)
{
    $type = $publication["subtypeDescription"] ?? "";
    $aggType = $publication["prism:aggregationType"] ?? "";

    if ($aggType === "Journal" && $type === "Article") {
        return "Journal article";
    } elseif (
        $aggType === "Conference Proceeding" &&
        $type === "Conference Paper"
    ) {
        return "Conference paper";
    } elseif ($aggType === "Book") {
        return "Book chapter";
    }

    return $type;
}

if (empty($publications)) {
    echo "No publications found or there was an error with the API request.";
}

// Fetch author details for each publication
$publicationsWithAuthors = [];
foreach ($publications as $publication) {
    $eid = $publication['eid'] ?? '';
    if (!empty($eid)) {
        $authors = fetchAuthors($baseUrl2, $apiKey, $eid);
        $publication['detailed_authors'] = $authors;
    }
    $publicationsWithAuthors[] = $publication;
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
    </style>
</head>
<body>

<?php if (!empty($publicationsWithAuthors)): ?>
    <div style="background-color: #f26522; color: white; padding: 16px; display: flex; justify-content: space-between; align-items: center; border-radius: 6px">
        <a href="https://orcid.org/0000-0002-2620-930X" target="_blank" style="font-size: 20px; font-weight: bold; color: white; text-decoration: none;">
            Works (<?php echo count($publicationsWithAuthors); ?>)
        </a>
    </div>
    <div id="publication-container"></div>
<?php else: ?>
    <p>No publications found or there was an error with the API request.</p>
<?php endif; ?>

<script>
const publications = <?php echo json_encode($publicationsWithAuthors); ?>;
const container = document.getElementById('publication-container');

function getDocumentTypeFull(pub) {
    const type = pub['subtypeDescription'] || '';
    const aggType = pub['prism:aggregationType'] || '';
    if (aggType === 'Conference Proceeding' && type === 'Conference Paper') {
        return 'Conference paper';
    }
    if (aggType === 'Journal' && type === 'Article') return 'Journal article';
    if (aggType === 'Book') return 'Book chapter';
    return type;
}

function formatContributors(pub) {
    if (pub.detailed_authors && pub.detailed_authors.length > 0) {
        const authorsList = [];

        pub.detailed_authors.forEach(author => {
            const indexedName = author['ce:indexed-name'];
            if (indexedName) {
                authorsList.push(indexedName);
            }
        });

        return authorsList.join('; ');
    }

    return pub['dc:creator'] || 'No contributors found';
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
        const contributorsHTML = formatContributors(pub);

        // === ISBN ===
        let isbnHTML = '';
        if (pub['prism:isbn']) {
            let isbns = pub['prism:isbn'];
            let values = [];

            if (Array.isArray(isbns)) {
                isbns.forEach(item => {
                    if (typeof item === 'object' && item['$']) {
                        if (Array.isArray(item['$'])) {
                            values.push(...item['$']);
                        } else {
                            values.push(...item['$'].split(/[\s,]+/));
                        }
                    } else {
                        values.push(item);
                    }
                });
            } else {
                values.push(isbns);
            }

            const cleaned = values.map(isbn => isbn.replace(/[^\dXx]/g, ''));
            const links = cleaned.map(isbn => `<a href="https://search.worldcat.org/th/search?q=bn:${isbn}" target="_blank">${isbn}</a>`);
            isbnHTML = `<p>Part of ISBN: ${links.join(' ')}</p>`;
        }

        // === ISSN ===
        let issnHTML = '';
        const issns = [];
        if (pub['prism:issn']) issns.push(pub['prism:issn']);
        if (pub['prism:eIssn']) issns.push(pub['prism:eIssn']);

        if (issns.length > 0) {
            const links = issns.map(issn => {
                const formatted = issn.replace(/(\d{4})(\d{4})/, '$1-$2');
                return `<a href="https://portal.issn.org/resource/ISSN/${formatted}" target="_blank">${issn}</a>`;
            });
            issnHTML = `<p>Part of ISSN: ${links.join(' ')}</p>`;
        }

        const doiHTML = doi ? `<p>DOI: <a href="https://doi.org/${doi}" target="_blank">${doi}</a></p>` : '';
        const contributorHTML = `<p>CONTRIBUTORS: ${contributorsHTML}</p>`;

        const html = `
            <div class="card">
                <div class="card-header"><b><div>${title}</div></b></div>
                <div class="card-content">
                    <p>${publicationName}</p>
                    <p>${year} | ${type}</p>
                    ${doiHTML}
                    <p>EID: ${eid}</p>
                    ${isbnHTML}
                    ${issnHTML}
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

renderCards(publications);
</script>

</body>
</html>
