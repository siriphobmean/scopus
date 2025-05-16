<?php
$baseUrl = "https://api.elsevier.com/content/search/scopus";
$apiKey = "ae7e84e02386105442a7e6d7919f5d4e";
$authorId = "23096399800"; // Saranya (CPE: 57184355700), Komsan (CPE: 23096399800) Scopus Author ID

function fetchPublications($baseUrl, $apiKey, $authorId)
{
    $allPublications = [];
    $start = 0;
    $count = 25;

    do {
        $queryParams = http_build_query([
            "query" => "AU-ID($authorId)",
            "apiKey" => $apiKey,
            "view" => "COMPLETE",
            "count" => $count,
            "start" => $start,
        ]);

        $url = $baseUrl . "?" . $queryParams;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["Accept: application/json"],
            CURLOPT_TIMEOUT => 15,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 429) {
            echo "Rate limit hit. Sleeping 60 seconds...\n";
            sleep(60);
            continue;
        }

        if ($response === false || $httpCode !== 200) {
            echo "Error: HTTP status $httpCode\n";
            break;
        }

        $data = json_decode($response, true);
        $entries = $data["search-results"]["entry"] ?? [];
        $totalResults = (int) ($data["search-results"]["opensearch:totalResults"] ?? 0);

        $allPublications = array_merge($allPublications, $entries);
        $start += $count;

        sleep(3);
    } while ($start < $totalResults);

    return [
        "totalResults" => count($allPublications),
        "publications" => $allPublications,
    ];
}

function extractAuthorsFromPublication($publication)
{
    $authors = [];

    if (isset($publication["author"])) {
        return $publication["author"];
    }

    if (isset($publication["dc:creator"])) {
        $creatorNames = explode(", ", $publication["dc:creator"]);
        foreach ($creatorNames as $index => $name) {
            $nameParts = explode(" ", $name);
            if (count($nameParts) > 1) {
                $surname = array_pop($nameParts);
                $givenName = implode(" ", $nameParts);
            } else {
                $surname = $name;
                $givenName = "";
            }

            $authors[] = [
                "@seq" => $index + 1,
                "ce:given-name" => $givenName,
                "ce:surname" => $surname,
                "@auid" => $publication["author-count"] ?? "",
            ];
        }
    }

    return $authors;
} // Check: Ok

$publicationsData = fetchPublications($baseUrl, $apiKey, $authorId);
$totalResults = $publicationsData["totalResults"];
$publications = $publicationsData["publications"];

$publicationsWithAuthors = [];
foreach ($publications as $publication) {
    $publication["detailed_authors"] = extractAuthorsFromPublication(
        $publication
    );

    $publicationsWithAuthors[] = $publication;
} // Check: Ok

$documentTypes = [];
foreach ($publications as $pub) {
    $type = $pub["subtypeDescription"] ?? "";
    $aggType = $pub["prism:aggregationType"] ?? "";

    // Conference paper
    if (
        in_array($aggType, ["Conference Proceeding", "Book Series"]) &&
        $type === "Conference Paper"
    ) {
        $documentTypes[] = "Conference paper";
    }

    // Journal article
    elseif (
        $aggType === "Journal" &&
        in_array($type, [
            "Article",
            "Short Survey",
            "Review",
            "Erratum",
            "Letter",
        ])
    ) {
        $documentTypes[] = "Journal article";
    }

    // Book chapter
    elseif (
        in_array($aggType, ["Book", "Book Series"]) &&
        in_array($type, ["Book Chapter", "Chapter"])
    ) {
        $documentTypes[] = "Book chapter";
    }

    // Whole book
    elseif ($aggType === "Book" && in_array($type, ["Book", "Edited Book"])) {
        $documentTypes[] = "Book";
    }

    // Editorial
    elseif ($type === "Editorial") {
        $documentTypes[] = "Editorial";
    }

    // Note, Letter, etc.
    elseif (in_array($type, ["Note", "Letter", "Erratum"])) {
        $documentTypes[] = $type;
    }
}

if (empty($documentTypes)) {
    $documentTypes[] = "Other";
} // Continue
$documentTypes = array_unique($documentTypes);
sort($documentTypes);
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

        /* .hover-link::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 2px;
            bottom: -2px;
            left: 0;
            background-color: #f26522;
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.4s ease-out;
            display: block;
            box-sizing: border-box;
        }

        .hover-link:hover::after {
            transform: scaleX(1);
        } */
         .hover-link::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2.6px;
            bottom: -2px;
            left: 0;
            background-color: #f26522;
            transform-origin: left;
            transition: width 0.4s ease-out;
            display: block;
            box-sizing: border-box;
        }

        .hover-link:hover::after {
            width: 100%;
        }
    </style>
</head>
<body>

<?php if (!empty($publicationsWithAuthors)): ?>
    <div style="background-color: #f26522; color: white; padding: 16px; display: flex; justify-content: space-between; align-items: center; border-radius: 6px">
        <div style="font-size: 20px; font-weight: bold;">
            <span id="pub-count">Works (<?php echo count(
                $publicationsWithAuthors
            ); ?>)</span>
        </div>
        <div class="controls-container">
            <!-- Filter Menu -->
            <div class="menu-container filter-menu">
                <div class="icon-btn" id="filter-icon"><i class="fas fa-filter"></i><span style="font-size: 16px">Filter</span></div>
                <div class="dropdown-menu-filter" id="filter-menu">
                    <div class="filter-option active" data-type="all">• All</div>
                    <?php foreach ($documentTypes as $type): ?>
                        <div class="filter-option" data-type="<?php echo htmlspecialchars(
                            $type
                        ); ?>">• <?php echo htmlspecialchars($type); ?></div>
                    <?php endforeach; ?>
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

    <?php if ($totalResults > 200): ?>
        <div style="color:rgb(0, 0, 0); padding: 8px; text-align: start; margin-top: 10px; margin-bottom: -20px">
            <text><strong>Note:</strong> Only 200 of the <?php echo $totalResults; ?> publications are currently displayed. To access the full list, please visit Elsevier's Scopus.</text>
        </div>
    <?php endif; ?>

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

    // Normalize type for case-insensitive comparison
    const typeLower = type.toLowerCase();
    const aggTypeLower = aggType.toLowerCase();

    // Conference Paper
    if (['conference proceeding', 'book series'].includes(aggTypeLower) && typeLower === 'conference paper') {
        return 'Conference paper';
    }

    // Journal Article
    if (aggTypeLower === 'journal' && ['article', 'short survey', 'review', 'erratum', 'letter'].includes(typeLower)) {
        return 'Journal article';
    }

    // Book Chapter
    if (['book', 'book series'].includes(aggTypeLower) && ['book chapter', 'chapter'].includes(typeLower)) {
        return 'Book chapter';
    }

    // Whole Book
    if (aggTypeLower === 'book' && ['book', 'edited book'].includes(typeLower)) {
        return 'Book';
    }

    // Editorial
    if (typeLower === 'editorial') {
        return 'Editorial';
    }

    // Note / Letter / Erratum (individual types)
    if (['note', 'letter', 'erratum'].includes(typeLower)) {
        return type.charAt(0).toUpperCase() + type.slice(1).toLowerCase(); // Capitalize only first letter
    }

    // Fallback: Return default "Other" if no match
    return 'Other';
} // Continue

function formatContributors(pub) {
    if (pub.detailed_authors && pub.detailed_authors.length > 0) {
        const names = pub.detailed_authors.map(author => author['authname'] || '');
        return names.join(', ');
    }

    return pub['dc:creator'] || 'No contributors found';
} // Check: Ok

function applyFiltersAndSort() {
    let filtered = [...publications];
    
    if (activeFilter !== 'all') {
        filtered = filtered.filter(pub => {
            const pubType = getDocumentTypeFull(pub);
            return pubType === activeFilter;
        });
    }
    
    if (activeSort === 'date') {
        filtered.sort((a, b) => {
            const dateA = new Date(a['prism:coverDate']);
            const dateB = new Date(b['prism:coverDate']);
            return sortOrderDate === 'asc' ? dateA - dateB : dateB - dateA;
        });
    } else if (activeSort === 'title') {
        filtered.sort((a, b) => {
            const titleA = a['dc:title'] || '';
            const titleB = b['dc:title'] || '';
            return sortOrderTitle === 'asc' ? titleA.localeCompare(titleB) : titleB.localeCompare(titleA);
        });
    } else if (activeSort === 'type') {
        filtered.sort((a, b) => {
            const typeA = getDocumentTypeFull(a);
            const typeB = getDocumentTypeFull(b);
            return sortOrderType === 'asc' ? typeA.localeCompare(typeB) : typeB.localeCompare(typeA);
        });
    }
    
    document.getElementById('pub-count').textContent = `Works (${filtered.length})`;
    
    renderCards(filtered);
} // Check: Ok

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
            const links = cleaned.map(isbn => {
                return `<a href="https://search.worldcat.org/th/search?q=bn:${isbn}" class="hover-link" target="_blank">${isbn}</a>`;
            });
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

        const cardHTML = `
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

        container.innerHTML += cardHTML;
    });
} // Check: Ok

function updateSortIcons(active, order) {
    const icons = {
        title: document.getElementById('sort-title-arrow'),
        date: document.getElementById('sort-date-arrow'),
        type: document.getElementById('sort-type-arrow')
    };

    Object.values(icons).forEach(icon => icon.style.display = 'none');

    if (icons[active]) {
        icons[active].style.display = 'inline';
        icons[active].classList.remove('fa-arrow-up', 'fa-arrow-down');
        icons[active].classList.add(order === 'asc' ? 'fa-arrow-up' : 'fa-arrow-down');
    }
} // Check: Ok

function sortPublications(sortBy, event) {
    event.preventDefault();
    event.stopPropagation();
    document.querySelector('.sort-menu').classList.remove('open');

    const sortOptions = {
        date: { order: sortOrderDate, update: () => { sortOrderDate = toggleSortOrder(sortOrderDate); activeSort = 'date'; updateSortIcons('date', sortOrderDate); } },
        title: { order: sortOrderTitle, update: () => { sortOrderTitle = toggleSortOrder(sortOrderTitle); activeSort = 'title'; updateSortIcons('title', sortOrderTitle); } },
        type: { order: sortOrderType, update: () => { sortOrderType = toggleSortOrder(sortOrderType); activeSort = 'type'; updateSortIcons('type', sortOrderType); } },
    };

    if (sortOptions[sortBy]) {
        sortOptions[sortBy].update();
    }

    applyFiltersAndSort();
}

function toggleSortOrder(order) {
    return order === 'desc' ? 'asc' : 'desc';
} // Check: Ok

updateSortIcons('date', sortOrderDate);
applyFiltersAndSort();
</script>
</body>
</html>
