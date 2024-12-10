<?php
// Start the session only if it hasn't been started yet
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include('dwos.php');

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get the logged-in owner's user_id
$user_id = $_SESSION['user_id'];

// Fetch Customer Details from the database using prepared statements
$stmt = $conn->prepare("SELECT user_name, image, password FROM users WHERE user_id = ? AND user_type = 'C'");
if (!$stmt) {
    echo "Prepare failed: (" . $conn->errno . ") " . $conn->error;
    exit();
}
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $user = $result->fetch_assoc();
} else {
    echo "Error fetching customer details: " . $conn->error; 
    exit();
}

// Fetch top 3 selling stations based on total quantity sold
$topSellingStationsSql = "
    SELECT st.station_id, st.station_name, SUM(o.quantity) AS total_sold
    FROM orders o
    JOIN products p ON o.product_id = p.product_id
    JOIN stations st ON p.station_id = st.station_id
    GROUP BY st.station_id, st.station_name
    ORDER BY total_sold DESC
    LIMIT 3";
$topSellingStationsResult = $conn->query($topSellingStationsSql);
if (!$topSellingStationsResult) {
    die("Error fetching top selling stations: " . $conn->error);
}

// Get recently purchased stations
function getRecentlyPurchasedStations($userId, $conn) {
    $query = "
        SELECT stations.station_id, stations.station_name, orders.order_date
        FROM orders
        INNER JOIN products ON orders.product_id = products.product_id
        INNER JOIN stations ON products.station_id = stations.station_id
        WHERE orders.user_id = ?
        ORDER BY orders.order_date DESC
        LIMIT 10";  // Limit to the most recent 10 purchases

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    // Check for results and return data
    $recentStations = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $recentStations[] = $row;
        }
    }

    $stmt->close();
    return $recentStations;
}

$recentStations = getRecentlyPurchasedStations($user_id, $conn);

if (isset($_GET['search'])) {
    $searchQuery = $_GET['search'];
    $searchQuery = mysqli_real_escape_string($conn, $searchQuery); // Sanitize input

    // Query to search stations by name
    $sql = "SELECT * FROM stations WHERE station_name LIKE '%$searchQuery%'";
    $result = mysqli_query($conn, $sql);

    if ($result && mysqli_num_rows($result) > 0) {
        echo "<h2>Search Results:</h2>";
        while ($row = mysqli_fetch_assoc($result)) {
            echo "<div>";
            echo "<h3>" . htmlspecialchars($row['station_name']) . "</h3>";
            echo "<p>" . htmlspecialchars($row['address']) . "</p>";
            echo "<a href='stationpage.php?station_id=" . $row['station_id'] . "'>View Station</a>";
            echo "</div><hr>";
        }
    } else {
        echo "<p>No stations found matching your query.</p>";
    }
}

// Handle the AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['query'])) {
    $query = mysqli_real_escape_string($conn, $_POST['query']);

    // Query to fetch station names and IDs matching the search input
    $sql = "SELECT station_id, station_name FROM stations WHERE station_name LIKE '%$query%' LIMIT 10";
    $result = mysqli_query($conn, $sql);

    $stations = [];
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $stations[] = $row;
        }
    }

    // Return the results as JSON
    echo json_encode($stations);
    exit;
}

// Fetch all stations
$stations = [];
$sql = "SELECT station_id, station_name FROM stations";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $stations[] = $row;
    }
}
$conn->close();
?>

<?php include 'customernavbar.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="customerpage.css" />
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet"/>
    <title>Customer Homepage</title>
</head>
<body>

<h1 style="font-size: 1.5em; color: blacl; text-align: left; margin-top: 20px; ">
    Welcome, <?php echo htmlspecialchars($user['user_name']); ?>!
</h1>
</head>

<div class="search-bar-container">
        <form method="GET" action="search_results.php">
            <input 
                type="text" 
                id="search-bar" 
                name="query" 
                placeholder="Search for a station." 
                autocomplete="off"
            />
            <ul id="stations-dropdown">
                <!-- Dynamically generated station list -->
                <?php foreach ($stations as $station): ?>
                    <li 
                        onclick="selectStation('<?php echo $station['station_name']; ?>', '<?php echo $station['station_id']; ?>')"
                    >
                        <?php echo htmlspecialchars($station['station_name']); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </form>
    </div>



<div class="home-container">
    <!-- Top Selling Stations Section -->
    <section class="top-selling">
        <h2>TOP SELLING STATIONS</h2>
        <ul class="list">
            <?php
            // Display the top selling stations with clickable backgrounds
            if ($topSellingStationsResult->num_rows > 0) {
                $rank = 1;
                while ($row = $topSellingStationsResult->fetch_assoc()) {
                    echo "<li class='home'>";
                    echo "<a href='products.php?station_id=" . urlencode($row['station_id']) . "' class='station-link'>";
                    echo "<span class='home-id'>{$rank}.</span>";
                    echo htmlspecialchars($row['station_name']);
                    echo "</a>";
                    echo "</li>";
                    $rank++;
                }
            } else {
                echo "<li class='home'>No sales found.</li>";
            }
            ?>
        </ul>
        <div class="show-all">
            <button class="btn" data-modal="top-selling-modal">Show All</button>
        </div>
    </section>

    <!-- Modal for All Top Selling Stations -->
    <div id="top-selling-modal" class="modal">
        <div class="modal-content">
            <span class="close-button" data-close="top-selling-modal">&times;</span>
            <h2>ALL TOP SELLING STATIONS</h2>
            <ul class="full-list">
                <?php
                // Fetch all stations, including those with no products sold
                $allStationsSql = "
                    SELECT st.station_id, st.station_name, 
                           COALESCE(SUM(o.quantity), 0) AS total_sold
                    FROM stations st
                    LEFT JOIN products p ON st.station_id = p.station_id
                    LEFT JOIN orders o ON o.product_id = p.product_id
                    GROUP BY st.station_id, st.station_name
                    ORDER BY total_sold DESC";
                $allStationsResult = $conn->query($allStationsSql);
                if ($allStationsResult && $allStationsResult->num_rows > 0) {
                    $rank = 1;
                    while ($row = $allStationsResult->fetch_assoc()) {
                        echo "<li class='station-item'>";
                        echo "<a href='products.php?station_id=" . urlencode($row['station_id']) . "' class='station-link'>";
                        echo "<span class='home-id'>{$rank}.</span>";
                        echo htmlspecialchars($row['station_name']);
                        echo "</a>";
                        echo "<p>" . htmlspecialchars($row['total_sold']) . " Products Sold</p>";
                        echo "</li>";
                        $rank++;
                    }
                } else {
                    echo "<li>No stations found.</li>";
                }
                ?>
            </ul>
        </div>
    </div>

<!-- Recently Purchased Stations Section -->
<section class="recent-stations">
    <h2>RECENTLY PURCHASED STATIONS</h2>
    <ul class="recent-list">
        <?php
        // Assuming $recentStations is an array of stations with 'station_id' and 'order_date' fields
        $displayedStations = [];  // Array to track displayed station IDs

        if (!empty($recentStations)) {
            $rank = 1;
            foreach ($recentStations as $station) {
                // Only show the station if it hasn't been displayed yet
                if (!in_array($station['station_id'], $displayedStations)) {
                    // Add the station to the displayed list
                    $displayedStations[] = $station['station_id'];  

                    // Display the station
                    echo "<li class='station-item'>";
                    echo "<a href='products.php?station_id=" . urlencode($station['station_id']) . "' class='station-link'>";
                    echo "<span class='home-id'>{$rank}.</span>";
                    echo htmlspecialchars($station['station_name']);
                    echo "</a>";
                    echo "<p>Last purchased: " . htmlspecialchars($station['order_date']) . "</p>";
                    echo "</li>";
                    $rank++;
                }
            }
        } else {
            echo "<li>No recent purchases found. Please try to discover Water Stations!</li>";
        }
        ?>
    </ul>
    <div class="show-all">
        <button class="btn" data-modal="recent-stations-modal">Show All</button>
    </div>
</section>

<!-- Modal for Recently Purchased Stations -->
<div id="recent-stations-modal" class="modal">
    <div class="modal-content">
        <span class="close-button" data-close="recent-stations-modal">&times;</span>
        <h2>ALL RECENTLY PURCHASED STATIONS</h2>
        <ul class="full-list">
            <?php
            $modalDisplayedStations = []; // To ensure no duplication in the modal

            if (!empty($recentStations)) {
                $rank = 1;
                foreach ($recentStations as $station) {
                    // Check if this station has already been displayed in the modal
                    if (!in_array($station['station_id'], $modalDisplayedStations)) {
                        // Add the station to the modal displayed list
                        $modalDisplayedStations[] = $station['station_id'];  

                        // Display the station in the modal
                        echo "<li class='station-item'>";
                        echo "<a href='products.php?station_id=" . urlencode($station['station_id']) . "' class='station-link'>";
                        echo "<span class='home-id'>{$rank}.</span>";
                        echo htmlspecialchars($station['station_name']);
                        echo "</a>";
                        echo "<p>Last purchased: " . htmlspecialchars($station['order_date']) . "</p>";
                        echo "</li>";
                        $rank++;
                    }
                }
            } else {
                echo "<li>No recent purchases or visits found. Please try to discover Water Stations!</li>";
            }
            ?>
        </ul>
    </div>
</div>


    <script>
        document.addEventListener("DOMContentLoaded", function() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function(position) {
                    const latitude = position.coords.latitude;
                    const longitude = position.coords.longitude;

                    // Send the location to the server
                    fetch('update_location.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            userId: <?php echo json_encode($user_id); ?>, // PHP variable for user ID
                            latitude: latitude,
                            longitude: longitude
                        }),
                    })
                    .then(response => response.json())
                    .then(data => {
                        console.log('Location updated:', data);
                    })
                    .catch((error) => {
                        console.error('Error updating location:', error);
                    });
                }, function(error) {
                    console.error("Error getting location: ", error);
                });
            } else {
                console.log("Geolocation is not supported by this browser.");
            }
        });

        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'flex';
            }
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'none';
            }
        }

        document.querySelectorAll('.show-all .btn').forEach(button => {
            button.addEventListener('click', () => {
                const modalId = button.getAttribute('data-modal');
                openModal(modalId);
            });
        });

        document.querySelectorAll('.close-button').forEach(button => {
            button.addEventListener('click', () => {
                const modalId = button.getAttribute('data-close');
                closeModal(modalId);
            });
        });

        window.addEventListener('click', (event) => {
            if (event.target.classList.contains('modal')) {
                closeModal(event.target.id);
            }
        });
    </script>
    
<script>
// Toggle the dropdown visibility
function toggleDropdown() {
    const dropdown = document.getElementById('stations-dropdown');
    dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
}

// Set the selected station in the input box
function selectStation(stationName) {
    document.getElementById('search-bar').value = stationName;
    document.getElementById('stations-dropdown').style.display = 'none';
}

document.addEventListener("DOMContentLoaded", () => {
            const searchBar = document.getElementById("search-bar");
            const dropdown = document.getElementById("stations-dropdown");

            // Show the dropdown only when the search bar is focused
            searchBar.addEventListener("focus", () => {
                dropdown.style.display = "block";
            });

            // Hide the dropdown when clicking outside the search bar or dropdown
            document.addEventListener("click", (event) => {
                if (
                    !searchBar.contains(event.target) &&
                    !dropdown.contains(event.target)
                ) {
                    dropdown.style.display = "none";
                }
            });

            // Prevent dropdown from hiding when clicking inside it
            dropdown.addEventListener("click", (event) => {
                event.stopPropagation();
            });

            // Filter and display stations in the dropdown
            searchBar.addEventListener("input", filterStations);
        });

        // Filter and display stations in the dropdown
        function filterStations() {
            const input = document.getElementById('search-bar');
            const filter = input.value.toLowerCase();
            const dropdown = document.getElementById('stations-dropdown');
            const items = dropdown.getElementsByTagName('li');
            let hasVisibleItems = false;

            for (let i = 0; i < items.length; i++) {
                const stationName = items[i].innerText.toLowerCase();
                if (stationName.includes(filter)) {
                    items[i].style.display = "block";
                    hasVisibleItems = true;
                } else {
                    items[i].style.display = "none";
                }
            }

            dropdown.style.display = hasVisibleItems ? "block" : "none";
        }

        // Set the selected station in the input box and redirect to products page
        function selectStation(stationName, stationId) {
            const searchBar = document.getElementById("search-bar");
            searchBar.value = stationName;
            document.getElementById("stations-dropdown").style.display = "none";
            // Redirect to the products page for the selected station
            window.location.href = `products.php?station_id=${stationId}`;
        }
</script>
</body>
</html>
