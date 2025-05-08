<?php
$baseUrl = "https://api.elsevier.com/content/search/scopus";
$baseUrl2 = "https://api.elsevier.com/content/abstract/eid";
$apiKey = "ae7e84e02386105442a7e6d7919f5d4e";
$authorId = "57184355700";

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

        .dropdown-menu {
            display: none;
            position: absolute;
            background-color: white;
            border: 1px solid #cccccc;
            border-radius: 6px;
            padding: 8px;
            top: 30px;
            right: 0px;
            min-width: 110px;
            /* z-index: 10; */
        }

        .dropdown-menu-filter {
            display: none;
            position: absolute;
            background-color: white;
            border: 1px solid #cccccc;
            border-radius: 6px;
            padding: 8px;
            top: 30px;
            right: 0px;
            min-width: 170px;
            /* z-index: 10; */
        }

        .icon-btn {
            font-size: 24px;
            cursor: pointer;
            transition: opacity 0.3s ease;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
        }

        .icon-btn:hover {
            opacity: 0.5;
        }

        .menu-container {
            position: relative;
        }

        .menu-container.open .dropdown-menu {
            display: block;
        }

        .menu-container.open .dropdown-menu-filter {
            display: block;
        }

        .sort-arrow {
            /* margin-left: 4px; */
            display: none;
        }

        .filter-option {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 12px;
            text-decoration: none;
            color: #000;
            gap: 10px;
        }

        .filter-option:hover {
            background-color: #f2f2f2;
            cursor: pointer;
        }

        .filter-option.active {
            color: #f26522;
            font-weight: bold;
        }

        .controls-container {
            display: flex;
            gap: 20px;
        }

        .dropdown-menu a {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 12px;
            text-decoration: none;
            color: #000;
            gap: 10px;
        }

        .dropdown-menu-filter a {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 12px;
            text-decoration: none;
            color: #000;
            gap: 10px;
        }

        .dropdown-menu a:hover {
            /* color: #f26522; */
            background-color: #f2f2f2;
        }

        .dropdown-menu-filter a:hover {
            /* color: #f26522; */
            background-color: #f2f2f2;
        }

        #sort-title-arrow,
        #sort-date-arrow,
        #sort-type-arrow {
            display: none;
        }

        .hover-link {
            color: #085c77;
            text-decoration: none;
            position: relative;
            cursor: pointer;
        }

        .hover-link:hover {
            color: #085c77 !important;
        }

        .hover-link::after {
            content: '';
            position: absolute;
            width: 100%;
            min-height: 2px;
            bottom: -2px;
            left: 0;
            background-color: #f26522;
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.4s ease-out;
        }

        .hover-link:hover::after {
            transform: scaleX(1);
            min-height: 2px;
        }
    </style>
</head>
<body>

<?php if (!empty($publicationsWithAuthors)): ?>
    <div style="background-color: #f26522; color: white; padding: 16px; display: flex; justify-content: space-between; align-items: center; border-radius: 6px">
        <div style="font-size: 20px; font-weight: bold;">
            <span id="pub-count">Works (<?php echo count($publicationsWithAuthors); ?>)</span>
        </div>
        <div class="controls-container">
            <!-- Filter Menu -->
            <div class="menu-container filter-menu">
                <div class="icon-btn" id="filter-icon"><i class="fas fa-filter"></i><span style="font-size: 16px">Filter</span></div>
                <div class="dropdown-menu-filter" id="filter-menu">
                    <div class="filter-option active" data-type="all">• All</div>
                    <div class="filter-option" data-type="Conference paper">• Conference paper</div>
                    <div class="filter-option" data-type="Journal article">• Journal article</div>
                    <div class="filter-option" data-type="Book chapter">• Book chapter</div>
                </div>
            </div>
            <!-- Sort Menu -->
            <div class="menu-container sort-menu">
                <div class="icon-btn" id="sort-icon"><i class="fas fa-bars"></i><span style="font-size: 16px">Sort</span></div>
                <div class="dropdown-menu" id="sort-menu">
                    <a href="#" onclick="sortPublications('title', event)">
                        <span>Title</span> <i id="sort-title-arrow" class="fas fa-arrow-down sort-arrow"></i>
                    </a>
                    <a href="#" onclick="sortPublications('date', event)">
                        <span>Date</span> <i id="sort-date-arrow" class="fas fa-arrow-down sort-arrow"></i>
                    </a>
                    <a href="#" onclick="sortPublications('type', event)">
                        <span>Type</span> <i id="sort-type-arrow" class="fas fa-arrow-down sort-arrow"></i>
                    </a>
                </div>
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
const fixedAuthorId = "<?php echo $authorId; ?>";

let sortOrderTitle = 'desc';
let sortOrderDate = 'desc';
let sortOrderType = 'desc';
let activeSort = 'date';
let activeFilter = 'all';

document.getElementById('sort-icon').addEventListener('click', function() {
    document.querySelector('.sort-menu').classList.toggle('open');
    document.querySelector('.filter-menu').classList.remove('open');
});

document.getElementById('filter-icon').addEventListener('click', function() {
    document.querySelector('.filter-menu').classList.toggle('open');
    document.querySelector('.sort-menu').classList.remove('open');
});

document.addEventListener('click', function(event) {
    if (!event.target.closest('.menu-container')) {
        document.querySelectorAll('.menu-container').forEach(menu => {
            menu.classList.remove('open');
        });
    }
});

document.querySelectorAll('.filter-option').forEach(option => {
    option.addEventListener('click', function() {
        const filterType = this.getAttribute('data-type');
        activeFilter = filterType;
        
        document.querySelectorAll('.filter-option').forEach(opt => {
            opt.classList.remove('active');
        });
        this.classList.add('active');
        
        document.querySelector('.filter-menu').classList.remove('open');
        
        applyFiltersAndSort();
    });
});

function getDocumentTypeFull(pub) {
    const type = pub['subtypeDescription'] || '';
    const aggType = pub['prism:aggregationType'] || '';

    // Conference Paper (หรือ Workshop Paper, Symposium Paper)
    if ((aggType === 'Conference Proceeding' || aggType === 'Book Series') && (type === 'Conference Paper' || type === 'Workshop Paper' || type === 'Symposium Paper')) {
        return 'Conference paper';
    }

    // Journal Article หรือ Review Article
    if (aggType === 'Journal' && (type === 'Article' || type === 'Review Article')) {
        return 'Journal article';
    }

    // Book Chapter (หรือ Book Series, Monograph)
    if (aggType === 'Book' && type === 'Book chapter') return 'Book chapter';
    if (aggType === 'Book Series' && type === 'Book Chapter') return 'Book chapter';
    if (aggType === 'Monograph' && type === 'Book Chapter') return 'Book chapter';

    if (!aggType || !type) {
        return 'Unknown document type';
    }

    return type;
}

function formatContributors(pub) {
    if (pub.detailed_authors && pub.detailed_authors.length > 0) {
        const authorsList = pub.detailed_authors.map(author => {
            const indexedName = author['ce:indexed-name'];
            const auid = author['@auid'] || author['auid'] || '';

            if (indexedName && auid) {
                return `<a href="https://www.scopus.com/authid/detail.uri?authorId=${auid}" class="hover-link" target="_blank">${indexedName}</a>`;
            } else if (indexedName) {
                return indexedName;
            } else {
                return 'Unknown Author';
            }
        });

        return authorsList.join('; ');
    }

    return pub['dc:creator'] || 'No contributors found';
}

function applyFiltersAndSort() {
    let filtered = [...publications];
    
    if (activeFilter !== 'all') {
        filtered = filtered.filter(pub => {
            const pubType = getDocumentTypeFull(pub);
            return pubType === activeFilter;
        });
    }
    
    if (activeSort === 'date') {
        if (sortOrderDate === 'asc') {
            filtered.sort((a, b) => new Date(a['prism:coverDate']) - new Date(b['prism:coverDate']));
        } else {
            filtered.sort((a, b) => new Date(b['prism:coverDate']) - new Date(a['prism:coverDate']));
        }
    } else if (activeSort === 'title') {
        if (sortOrderTitle === 'asc') {
            filtered.sort((a, b) => a['dc:title'].localeCompare(b['dc:title']));
        } else {
            filtered.sort((a, b) => b['dc:title'].localeCompare(a['dc:title']));
        }
    } else if (activeSort === 'type') {
        if (sortOrderType === 'asc') {
            filtered.sort((a, b) => getDocumentTypeFull(a).localeCompare(getDocumentTypeFull(b)));
        } else {
            filtered.sort((a, b) => getDocumentTypeFull(b).localeCompare(getDocumentTypeFull(a)));
        }
    }
    
    document.getElementById('pub-count').textContent = `Works (${filtered.length})`;
    
    renderCards(filtered);
}

function renderCards(data) {
    container.innerHTML = '';
    if (data.length === 0) {
        container.innerHTML = '<div style="margin-top: 20px; text-align: center;">No publications found matching the selected filter.</div>';
        return;
    }
    
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
            const links = cleaned.map(isbn => `<a href="https://search.worldcat.org/th/search?q=bn:${isbn}" class="hover-link" target="_blank">${isbn}</a>`);
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
                return `<a href="https://portal.issn.org/resource/ISSN/${formatted}" class="hover-link" target="_blank">${issn}</a>`;
            });
            issnHTML = `<p>Part of ISSN: ${links.join(' ')}</p>`;
        }

        const doiHTML = doi ? `<p>DOI: <a href="https://doi.org/${doi}" class="hover-link" target="_blank">${doi}</a></p>` : '';
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
                    <strong style="color: #f26522;">Source:</strong>
                    <a 
                        href="https://www.scopus.com/authid/detail.uri?authorId=${fixedAuthorId}" 
                        target="_blank" 
                        class="hover-link"
                        style="text-decoration: none; color: black !important;">
                        Elsevier's Scopus
                    </a>
                </div>
            </div>`;
        container.innerHTML += html;
    });
}

function updateSortIcons(active, order) {
    const titleIcon = document.getElementById('sort-title-arrow');
    const dateIcon = document.getElementById('sort-date-arrow');
    const typeIcon = document.getElementById('sort-type-arrow');

    titleIcon.style.display = 'none';
    dateIcon.style.display = 'none';
    typeIcon.style.display = 'none';

    if (active === 'date') {
        dateIcon.style.display = 'inline';
        dateIcon.classList.remove('fa-arrow-up', 'fa-arrow-down');
        dateIcon.classList.add(order === 'asc' ? 'fa-arrow-up' : 'fa-arrow-down');
    } else if (active === 'title') {
        titleIcon.style.display = 'inline';
        titleIcon.classList.remove('fa-arrow-up', 'fa-arrow-down');
        titleIcon.classList.add(order === 'asc' ? 'fa-arrow-up' : 'fa-arrow-down');
    } else if (active === 'type') {
        typeIcon.style.display = 'inline';
        typeIcon.classList.remove('fa-arrow-up', 'fa-arrow-down');
        typeIcon.classList.add(order === 'asc' ? 'fa-arrow-up' : 'fa-arrow-down');
    }
}

function sortPublications(sortBy, event) {
    event.preventDefault();
    event.stopPropagation();
    document.querySelector('.sort-menu').classList.remove('open');
    
    if (sortBy === 'date') {
        sortOrderDate = sortOrderDate === 'desc' ? 'asc' : 'desc';
        activeSort = 'date';
        updateSortIcons('date', sortOrderDate);
    } else if (sortBy === 'title') {
        sortOrderTitle = sortOrderTitle === 'desc' ? 'asc' : 'desc';
        activeSort = 'title';
        updateSortIcons('title', sortOrderTitle);
    } else if (sortBy === 'type') {
        sortOrderType = sortOrderType === 'desc' ? 'asc' : 'desc';
        activeSort = 'type';
        updateSortIcons('type', sortOrderType);
    }

    applyFiltersAndSort();
}

updateSortIcons('date', sortOrderDate);
applyFiltersAndSort();
</script>

</body>
</html>
