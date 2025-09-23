<?php
session_start();
require '../config.php';

// Check if the admin is logged in
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_username'])) {
    // Redirect to login page if not logged in
    header("Location:login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" href="../../img/logo1.png" type="image/png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../css/admin/map.css">
    <title>Vaccination Mapping</title>
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/animate.css@4.1.1/animate.min.css">
    
</head>

<body>
<?php include 'sidebar.php'; ?>
<!-- Header -->
    <div class="content">
        <div class="header-content">
            <h1><i class="fas fa-globe"></i>Map of Magallanes, Cavite</h1>
            <div class="breadcrumb">
                <span>Admin</span> <i class="fas fa-chevron-right mx-2"></i> <span class="active">Map</span>
            </div>
        </div>
        
        <div class="map-container">
            <div id="map"></div>
            <div class="vaccine-filter">
                <select id="vaccine-select">
                    <option value="density">Population Density</option>
                    <!-- Vaccine options will be added dynamically -->
                </select>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
    const dropdownToggles = document.querySelectorAll('.dropdown > a');
    
    dropdownToggles.forEach(function(toggle) {
        toggle.addEventListener('click', function(e) {
            if (window.innerWidth > 768) {
                e.preventDefault();
                const dropdownMenu = this.nextElementSibling;
                
                // Close all other dropdowns
                document.querySelectorAll('.dropdown-menu').forEach(function(menu) {
                    if (menu !== dropdownMenu) {
                        menu.style.display = 'none';
                    }
                });
                
                // Toggle current dropdown
                if (dropdownMenu.style.display === 'block') {
                    dropdownMenu.style.display = 'none';
                } else {
                    dropdownMenu.style.display = 'block';
                }
            }
        });
    });

    // Close dropdowns when clicking outside (for desktop)
    document.addEventListener('click', function(e) {
        if (window.innerWidth > 768) {
            if (!e.target.closest('.dropdown')) {
                document.querySelectorAll('.dropdown-menu').forEach(function(menu) {
                    menu.style.display = 'none';
                });
            }
        }
    });

    // Make dropdowns stay open on mobile when sidebar is expanded
    const sidebar = document.getElementById('sidebar');
    sidebar.addEventListener('mouseleave', function() {
        if (window.innerWidth > 768) {
            document.querySelectorAll('.dropdown-menu').forEach(function(menu) {
                menu.style.display = 'none';
            });
        }
    });
    // Toggle sidebar function
    function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const content = document.getElementById('main-content');
            sidebar.classList.toggle('collapsed');
            
            // Save state to localStorage
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        }
        
        // Check saved state on page load
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            
            if (isCollapsed) {
                sidebar.classList.add('collapsed');
            } else {
                sidebar.classList.remove('collapsed');
            }
        });
// Global variables
let currentFilter = 'density';
let availableVaccines = [];
let map;
let geojsonLayer;

// Initialize the map when the page loads
document.addEventListener('DOMContentLoaded', function() {
    initializeMap();
    fetchVaccineData();
});

// Initialize the map
function initializeMap() {
    // Set the initial view to Magallanes, Cavite
    map = L.map('map').setView([14.1750, 120.7470], 11);
    
    // Add OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);
    
    // Add legend
    addLegend();
}

// Fetch vaccine data from the API
function fetchVaccineData() {
    // Use absolute path to vaccine_data.php
    const url = window.location.pathname.includes('/map.php') 
        ? 'vaccine_data.php' 
        : '../vaccine_data.php';
    
    fetch(url)
        .then(response => {
            if (!response.ok) {
                return response.text().then(text => {
                    throw new Error(`Server responded with ${response.status}: ${text}`);
                });
            }
            return response.json();
        })
        .then(data => {
            if (!data || !data.features) {
                throw new Error('Invalid data format received from server');
            }
            
            // Log the vaccines data to check what is being returned
            console.log('Vaccine Data:', data.features[0].properties.vaccines);
            
            // Get available vaccines from first feature (if exists)
            if (data.features.length > 0 && data.features[0].properties.vaccines) {
                availableVaccines = Object.keys(data.features[0].properties.vaccines);
                
                // Log available vaccines to check if all are being included
                console.log('Available Vaccines:', availableVaccines);
                
                populateVaccineFilter();
            }
            
            addGeoJsonLayer(data);
        })
        .catch(error => {
            console.error('Error loading vaccine data:', error);
            alert('Failed to load vaccination data: ' + error.message);
            
            // Add a marker to show where the error occurred
            L.marker([14.1750, 120.7470]).addTo(map)
                .bindPopup(`<b>Error</b><br>${error.message}`)
                .openPopup();
        });
}

// Add GeoJSON layer to the map
function addGeoJsonLayer(geojsonData) {
    // Remove existing layer if it exists
    if (geojsonLayer) {
        map.removeLayer(geojsonLayer);
    }
    
    // Style function for the GeoJSON features
    function style(feature) {
        return {
            fillColor: getColor(getValue(feature)),
            weight: 2,
            opacity: 1,
            color: 'white',
            dashArray: '3',
            fillOpacity: 0.7
        };
    }
    
    // Function to highlight features on hover
    function highlightFeature(e) {
        const layer = e.target;
        
        layer.setStyle({
            weight: 5,
            color: '#666',
            dashArray: '',
            fillOpacity: 0.7
        });
        
        layer.bringToFront();
        updatePopup(layer);
    }
    
    // Function to reset highlight
    function resetHighlight(e) {
        geojsonLayer.resetStyle(e.target);
    }
    
    // Function to update popup content
    function updatePopup(layer) {
        const feature = layer.feature;
        let popupContent = `<div><h4>${feature.properties.name}</h4>`;
        
        if (currentFilter === 'density') {
            popupContent += `<p>Total Children(0-12 months): <strong>${feature.properties.density}</strong></p>`;
        } else {
            const vaccineName = document.getElementById('vaccine-select')
                              .selectedOptions[0].text;
            const coverage = feature.properties.vaccines[currentFilter] || 0;
            const vaccinatedCount = Math.round(feature.properties.density * coverage / 100);
            
            popupContent += `
                <p>Vaccine: <strong>${vaccineName}</strong></p>
                <p>Coverage: <strong>${coverage}%</strong></p>
                <p>Total Children(0-12 months): <strong>${feature.properties.density}</strong></p>
                <p>Vaccinated Children(0-12 months): <strong>${vaccinatedCount}</strong></p>
            `;
        }
        
        popupContent += `</div>`;
        layer.bindPopup(popupContent);
    }
    
// Create GeoJSON layer
    geojsonLayer = L.geoJson(geojsonData, {
        style: style,
        onEachFeature: function(feature, layer) {
            // Add label to each barangay
            if (feature.geometry && feature.geometry.type === 'Polygon') {
                const center = getPolygonCenter(feature.geometry.coordinates[0]);
                const label = L.marker(center, {
                    icon: L.divIcon({
                        className: 'barangay-label',
                        html: feature.properties.name,
                        iconSize: [100, 20] // Adjust size as needed
                    }),
                    zIndexOffset: 1000 // Make sure labels appear above polygons
                }).addTo(map);
            }
            
            layer.on({
                mouseover: highlightFeature,
                mouseout: resetHighlight,
                click: function(e) {
                    updatePopup(e.target);
                    e.target.openPopup();
                }
            });
            
            // Add popup on load if this is the first feature
            if (geojsonData.features.indexOf(feature) === 0) {
                updatePopup(layer);
            }
        }
    }).addTo(map);
    
    // Fit map to the bounds of the GeoJSON data
    if (geojsonData.features.length > 0) {
        map.fitBounds(geojsonLayer.getBounds());
    }
}

// Helper function to calculate the center of a polygon
function getPolygonCenter(coords) {
    let x = 0, y = 0;
    for (let i = 0; i < coords.length; i++) {
        x += coords[i][0];
        y += coords[i][1];
    }
    return [y / coords.length, x / coords.length];
}

// Populate the vaccine filter dropdown
function populateVaccineFilter() {
    const select = document.getElementById('vaccine-select');
    
    // Clear existing options except the first one
    while (select.options.length > 1) {
        select.remove(1);
    }
    
    // Add vaccine options with proper formatting
    availableVaccines.forEach(vaccine => {
        const formattedName = vaccine.replace(/_/g, ' ');
        
        const option = document.createElement('option');
        option.value = vaccine;
        option.textContent = formattedName;
        select.appendChild(option);
    });
    
    // Add event listener for filter changes
    select.addEventListener('change', function(e) {
        currentFilter = e.target.value;
        geojsonLayer.setStyle(style);
        updateLegend();
    });
}

// Get the appropriate value based on current filter
function getValue(feature) {
    if (currentFilter === 'density') {
        return feature.properties.density;
    } else {
        // Calculate actual number of vaccinated infants
        const coverage = feature.properties.vaccines[currentFilter] || 0;
        return Math.round(feature.properties.density * coverage / 100);
    }
}

// Get color based on value and current filter
function getColor(value) {
    if (value === 0) {
        return '#FFFFFF';  // White color for zero value
    }
    return value > 100 ? '#800026' :
           value > 50  ? '#BD0026' :
           value > 20  ? '#E31A1C' :
           value > 10  ? '#FC4E2A' :
           value > 5   ? '#FD8D3C' :
           value > 1   ? '#FEB24C' :
                         '#FFEDA0';
}

// Add legend to the map
function addLegend() {
    const legend = L.control({position: 'bottomright'});
    
    legend.onAdd = function() {
        const div = L.DomUtil.create('div', 'legend');
        updateLegendContent(div);
        return div;
    };
    
    legend.addTo(map);
}

// Update legend content based on current filter
function updateLegend() {
    const legend = document.querySelector('.legend');
    if (legend) {
        updateLegendContent(legend);
    }
}

function updateLegendContent(container) {
    if (currentFilter === 'density') {
        const grades = [0, 1, 5, 10, 20, 50, 100];
        const labels = grades.map((grade, index) => {
            const nextGrade = grades[index + 1];
            // Handle the zero value as white color
            return `<i style="background:${getColor(grade === 0 ? 0 : grade + 1)}"></i> ${grade}${nextGrade ? '&ndash;' + nextGrade : '+'}`;
        });
        container.innerHTML = '<strong>Children(0-12 months) Population/ Vaccination Coverage for</strong><br>' + labels.join('<br>');
    } else {
        const grades = [0, 40, 50, 60, 70, 80, 90];
        const labels = grades.map((grade, index) => {
            const nextGrade = grades[index + 1];
            // Handle the zero value as white color
            return `<i style="background:${getColor(grade === 0 ? 0 : grade + 1)}"></i> ${grade}${nextGrade ? '&ndash;' + nextGrade : '+'}%`;
        });
        const vaccineName = document.getElementById('vaccine-select').selectedOptions[0].text;
        container.innerHTML = `<strong>${vaccineName} Coverage</strong><br>` + labels.join('<br>');
    }
}
</script>

</body>
</html>
