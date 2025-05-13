<?php
$apiKey = "ae7e84e02386105442a7e6d7919f5d4e";
$baseUrl = "https://api.elsevier.com/content/search/scopus";
$baseUrl2 = "https://api.elsevier.com/content/abstract/eid";
$authorId = "23096399800";

// วิธีที่ 1: ใช้ API ของ Scopus แต่เพิ่มการจัดการกับ rate limiting
function fetchPublications($baseUrl, $apiKey, $authorId)
{
    $queryParams = http_build_query([
        "query" => "AU-ID($authorId)",
        "apiKey" => $apiKey,
        "view" => "COMPLETE", // ขอข้อมูลแบบสมบูรณ์รวมถึงผู้เขียน
    ]);

    $url = $baseUrl . "?" . $queryParams;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);

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

// วิธีที่ 2: ดึงข้อมูลผู้เขียนจาก API หลัก โดยไม่ต้องเรียก API เพิ่มเติม
function extractAuthorsFromPublication($publication) 
{
    $authors = [];
    
    // ตรวจสอบว่ามีข้อมูลผู้เขียนในผลลัพธ์หลักหรือไม่
    if (isset($publication['author'])) {
        return $publication['author'];
    }
    
    // สร้างข้อมูลผู้เขียนจากฟิลด์ dc:creator ถ้าไม่มีข้อมูลผู้เขียนโดยตรง
    if (isset($publication['dc:creator'])) {
        $creatorNames = explode(', ', $publication['dc:creator']);
        foreach ($creatorNames as $index => $name) {
            // แยกชื่อและนามสกุล (ถ้าเป็นไปได้)
            $nameParts = explode(' ', $name);
            if (count($nameParts) > 1) {
                $surname = array_pop($nameParts);
                $givenName = implode(' ', $nameParts);
            } else {
                $surname = $name;
                $givenName = '';
            }
            
            $authors[] = [
                '@seq' => $index + 1,
                'ce:given-name' => $givenName,
                'ce:surname' => $surname,
                '@auid' => $publication['author-count'] ?? ''
            ];
        }
    }
    
    return $authors;
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
    // วิธีที่ 1: ใช้ข้อมูลผู้เขียนจากผลลัพธ์หลัก
    $publication['detailed_authors'] = extractAuthorsFromPublication($publication);
    
    $publicationsWithAuthors[] = $publication;
}
echo '<pre>';
print_r($publicationsWithAuthors);
echo '</pre>';
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
        
        .loading {
            display: none;
            text-align: center;
            padding: 20px 0;
        }
        
        .loading-progress {
            width: 100%;
            height: 4px;
            background-color: #eee;
            position: relative;
            margin-top: 10px;
        }
        
        .loading-bar {
            height: 100%;
            background-color: #f26522;
            width: 0%;
            transition: width 0.3s;
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
    <div class="loading">
        <p>Loading author information...</p>
        <div class="loading-progress">
            <div class="loading-bar" id="progress-bar"></div>
        </div>
    </div>
<?php else: ?>
    <p>No publications found or there was an error with the API request.</p>
<?php endif; ?>

<script>
const publications = <?php echo json_encode($publicationsWithAuthors); ?>;
const container = document.getElementById('publication-container');
const loadingDiv = document.querySelector('.loading');
const progressBar = document.getElementById('progress-bar');

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

// function formatContributors(pub) {
//     if (pub.detailed_authors && pub.detailed_authors.length > 0) {
//         let html = '';
        
//         pub.detailed_authors.forEach((author, index) => {
//             const givenName = author['ce:given-name'] || '';
//             const surname = author['ce:surname'] || '';
//             const fullName = `${givenName} ${surname}`.trim();
//             const auid = author['@auid'] || author['auid'] || '';
            
//             html += `${index > 0 ? ', ' : ''}`;
            
//             if (auid && auid.includes('AU-ID:')) {
//                 html += `<a href="https://www.scopus.com/authid/detail.uri?authorId=${auid.replace('AU-ID:', '')}" target="_blank">${fullName}</a>`;
//             } else if (auid) {
//                 // ถ้าเป็น ID จาก OpenAlex
//                 if (auid.startsWith('https://openalex.org/')) {
//                     html += `<a href="${auid}" target="_blank">${fullName}</a>`;
//                 } else {
//                     html += `<a href="https://www.scopus.com/authid/detail.uri?authorId=${auid}" target="_blank">${fullName}</a>`;
//                 }
//             } else {
//                 html += fullName;
//             }
//         });
        
//         return html;
//     }
    
//     // Fallback to original creator if detailed authors not available
//     return pub['dc:creator'] || 'No contributors found';
// }
function formatContributors(pub) {
    if (pub.detailed_authors && pub.detailed_authors.length > 0) {
        let html = '';

        pub.detailed_authors.forEach((author, index) => {
            const fullName = author['authname'] || '';
            const authid = author['authid'] || '';

            html += `${index > 0 ? ', ' : ''}`;

            if (authid) {
                html += `<a href="https://www.scopus.com/authid/detail.uri?authorId=${authid}" target="_blank">${fullName}</a>`;
            } else {
                html += fullName;
            }
        });

        return html;
    }

    // Fallback to original creator if detailed authors not available
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
        const contributorsText = formatContributors(pub);

        const doiHTML = doi ? `<p>DOI: <a href="https://doi.org/${doi}" target="_blank">${doi}</a></p>` : '';
        const contributorHTML = `<p>CONTRIBUTORS: ${contributorsText}</p>`;

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

renderCards(publications);
</script>

</body>
</html>
