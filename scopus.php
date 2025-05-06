<?php
$baseUrl = "https://api.elsevier.com/content/search/scopus";
$baseUrl2 = "https://api.elsevier.com/content/abstract/eid";
$apiKey = "ae7e84e02386105442a7e6d7919f5d4e";
$authorId = "23096399800";

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

        .card-footer {
            background-color: white;
            padding: 8px 16px;
            border-top: 1px solid #cccccc;
        }

        #sort-menu {
            display: none;
            position: absolute;
            background-color: white;
            border: 1px solid #cccccc;
            border-radius: 6px;
            padding: 8px;
            top: 30px;
            right: 0px;
            width: 80px;
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
            transition: opacity 0.3s ease;
        }

        #hamburger-icon:hover {
            opacity: 0.5;
        }

        .hamburger-menu {
            position: relative;
        }

        .hamburger-menu.open #sort-menu {
            display: block;
        }

        #sort-date-arrow,
        #sort-type-arrow {
            margin-left: 4px;
            display: none;
        }
    </style>
</head>
<body>

<?php if (!empty($publicationsWithAuthors)): ?>
    <div style="background-color: #A67436; color: white; padding: 16px; display: flex; justify-content: space-between; align-items: center; border-radius: 6px">
        <!-- <div style="font-size: 20px; font-weight: bold;">
            Works (<?php echo count($publicationsWithAuthors); ?>)
        </div> -->
        <a href="https://orcid.org/0000-0002-2620-930X" target="_blank" style="font-size: 20px; font-weight: bold; color: white; text-decoration: none;">
            Works (<?php echo count($publicationsWithAuthors); ?>)
        </a>
        <div class="hamburger-menu">
            <i id="hamburger-icon" class="fas fa-bars"></i>
            <div id="sort-menu">
                <a href="#" onclick="sortPublications('date', event)">
                    Date <i id="sort-date-arrow" class="fas fa-arrow-down"></i>
                </a>
                <a href="#" onclick="sortPublications('type', event)">
                    Type <i id="sort-type-arrow" class="fas fa-arrow-down"></i>
                </a>
            </div>
        </div>
    </div>
    <div id="publication-container"></div>
<?php else: ?>
    <p>No publications found or there was an error with the API request.</p>
<?php endif; ?>

<script>
const publications = <?php echo json_encode($publicationsWithAuthors); ?>;
const container = document.getElementById('publication-container');

let sortOrderDate = 'desc';
let sortOrderType = 'desc';
let activeSort = 'date';

document.getElementById('hamburger-icon').addEventListener('click', function () {
    document.querySelector('.hamburger-menu').classList.toggle('open');
});

document.addEventListener('click', function (event) {
    const menu = document.querySelector('.hamburger-menu');
    if (!menu.contains(event.target)) {
        menu.classList.remove('open');
    }
});

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
        const contributorHTML = `<p">CONTRIBUTORS: ${contributorsHTML}</p>`;

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

function updateSortIcons(active, order) {
    const dateIcon = document.getElementById('sort-date-arrow');
    const typeIcon = document.getElementById('sort-type-arrow');

    dateIcon.style.display = 'none';
    typeIcon.style.display = 'none';

    if (active === 'date') {
        dateIcon.style.display = 'inline';
        dateIcon.classList.remove('fa-arrow-up', 'fa-arrow-down');
        dateIcon.classList.add(order === 'asc' ? 'fa-arrow-up' : 'fa-arrow-down');
    } else if (active === 'type') {
        typeIcon.style.display = 'inline';
        typeIcon.classList.remove('fa-arrow-up', 'fa-arrow-down');
        typeIcon.classList.add(order === 'asc' ? 'fa-arrow-up' : 'fa-arrow-down');
    }
}

function sortPublications(sortBy, event) {
    event.preventDefault();
    event.stopPropagation();
    document.querySelector('.hamburger-menu').classList.remove('open');
    let sorted = [...publications];

    if (sortBy === 'date') {
        if (sortOrderDate === 'desc') {
            sorted.sort((a, b) => new Date(a['prism:coverDate']) - new Date(b['prism:coverDate']));
            sortOrderDate = 'asc';
        } else {
            sorted.sort((a, b) => new Date(b['prism:coverDate']) - new Date(a['prism:coverDate']));
            sortOrderDate = 'desc';
        }
        activeSort = 'date';
        updateSortIcons('date', sortOrderDate);
    } else if (sortBy === 'type') {
        if (sortOrderType === 'desc') {
            sorted.sort((a, b) => getDocumentTypeFull(a).localeCompare(getDocumentTypeFull(b)));
            sortOrderType = 'asc';
        } else {
            sorted.sort((a, b) => getDocumentTypeFull(b).localeCompare(getDocumentTypeFull(a)));
            sortOrderType = 'desc';
        }
        activeSort = 'type';
        updateSortIcons('type', sortOrderType);
    }

    renderCards(sorted);
}

updateSortIcons('date', sortOrderDate);
renderCards(publications);
</script>

</body>
</html>
