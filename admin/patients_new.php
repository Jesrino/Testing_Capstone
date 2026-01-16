<?php
require_once "../includes/guards.php";
if (!in_array(role(), ['admin', 'dentist', 'dentist_pending'])) {
  header('Location: ' . $base_url . '/public/login.php');
  exit;
}
include("../includes/header.php");
?>

<div class="container">
    <h1>All Patients</h1>

    <!-- Search and Filter Controls -->
    <div class="controls-section">
        <div class="search-box">
            <input type="text" id="searchInput" placeholder="Search by name..." class="search-input">
            <button id="clearSearch" class="btn-clear">Clear</button>
        </div>
        <div class="filter-controls">
            <select id="typeFilter" class="filter-select">
                <option value="">All Types</option>
                <option value="registered">Registered</option>
                <option value="walkin">Walk-in</option>
            </select>
            <select id="groupFilter" class="filter-select">
                <option value="">No Grouping</option>
                <option value="month">Group by Month</option>
            </select>
        </div>
    </div>

    <!-- Patients Table -->
    <div class="patients-table-container">
        <table class="patients-table" id="patientsTable">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Contact Info</th>
                    <th>Last Visit</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="patientsTableBody">
                <!-- Data will be loaded via JavaScript -->
            </tbody>
        </table>
    </div>

    <!-- Loading indicator -->
    <div id="loadingIndicator" class="loading-indicator">
        <p>Loading patients...</p>
    </div>

    <!-- No patients message -->
    <div id="noPatientsMessage" class="no-patients" style="display: none;">
        <div class="no-patients-icon">
            <img src="<?php echo $base_url; ?>/assets/images/patients_icon.svg" alt="No Patients">
        </div>
        <h3>No patients found</h3>
        <p>No clients have scheduled appointments yet.</p>
    </div>
</div>

<style>
.controls-section {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    gap: 20px;
    flex-wrap: wrap;
}

.search-box {
    display: flex;
    gap: 10px;
    align-items: center;
}

.search-input {
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 14px;
    min-width: 250px;
}

.btn-clear {
    background: #6b7280;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
}

.btn-clear:hover {
    background: #4b5563;
}

.filter-controls {
    display: flex;
    gap: 10px;
}

.filter-select {
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 14px;
    background: white;
}

.patients-table-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    border: 1px solid #e5e7eb;
}

.patients-table {
    width: 100%;
    border-collapse: collapse;
}

.patients-table th {
    background: #f9fafb;
    padding: 12px 16px;
    text-align: left;
    font-weight: 600;
    color: #374151;
    border-bottom: 1px solid #e5e7eb;
    font-size: 14px;
}

.patients-table td {
    padding: 12px 16px;
    border-bottom: 1px solid #f3f4f6;
    font-size: 14px;
}

.patients-table tbody tr:hover {
    background: #f9fafb;
}

.patient-name {
    font-weight: 500;
    color: #1f2937;
}

.patient-type {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.patient-type.registered {
    background: #dbeafe;
    color: #1e40af;
}

.patient-type.walkin {
    background: #fef3c7;
    color: #92400e;
}

.contact-info {
    color: #6b7280;
}

.contact-info div {
    margin-bottom: 2px;
}

.last-visit {
    color: #374151;
}

.btn-view {
    background: #3b82f6;
    color: white;
    padding: 6px 12px;
    border-radius: 4px;
    text-decoration: none;
    font-size: 12px;
    font-weight: 500;
    transition: background 0.2s;
}

.btn-view:hover {
    background: #2563eb;
}

.loading-indicator {
    text-align: center;
    padding: 40px;
    color: #6b7280;
}

.no-patients {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    margin-top: 20px;
}

.no-patients-icon img {
    width: 64px;
    height: 64px;
    opacity: 0.5;
    margin-bottom: 20px;
}

.no-patients h3 {
    margin: 0 0 10px 0;
    color: #1f2937;
}

.no-patients p {
    margin: 0;
    color: #6b7280;
}

.group-header {
    background: #f3f4f6;
    font-weight: 600;
    color: #374151;
    padding: 8px 16px;
    border-bottom: 1px solid #e5e7eb;
}

.group-header td {
    padding: 12px 16px;
    font-size: 16px;
}
</style>

<script>
let allPatients = [];
let filteredPatients = [];

document.addEventListener('DOMContentLoaded', function() {
    loadPatients();

    // Search functionality
    document.getElementById('searchInput').addEventListener('input', filterPatients);
    document.getElementById('clearSearch').addEventListener('click', function() {
        document.getElementById('searchInput').value = '';
        filterPatients();
    });

    // Filter functionality
    document.getElementById('typeFilter').addEventListener('change', filterPatients);
    document.getElementById('groupFilter').addEventListener('change', loadPatients);
});

function loadPatients() {
    const loadingIndicator = document.getElementById('loadingIndicator');
    const noPatientsMessage = document.getElementById('noPatientsMessage');
    const table = document.getElementById('patientsTable');

    loadingIndicator.style.display = 'block';
    noPatientsMessage.style.display = 'none';
    table.style.display = 'none';

    const groupBy = document.getElementById('groupFilter').value;
    const url = `<?php echo $base_url; ?>/api/patients.php?action=get_patients${groupBy ? '&group_by=' + groupBy : ''}`;

    fetch(url)
        .then(response => response.json())
        .then(data => {
            loadingIndicator.style.display = 'none';
            table.style.display = 'table';

            if (data.patients && Object.keys(data.patients).length > 0) {
                if (groupBy === 'month') {
                    allPatients = [];
                    Object.values(data.patients).forEach(group => {
                        allPatients = allPatients.concat(group);
                    });
                } else {
                    allPatients = data.patients;
                }
                filteredPatients = [...allPatients];
                renderPatients(data.patients, groupBy);
            } else {
                noPatientsMessage.style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Error loading patients:', error);
            loadingIndicator.style.display = 'none';
            noPatientsMessage.style.display = 'block';
        });
}

function filterPatients() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const typeFilter = document.getElementById('typeFilter').value;

    filteredPatients = allPatients.filter(patient => {
        const matchesSearch = patient.name.toLowerCase().includes(searchTerm);
        const matchesType = !typeFilter || patient.type === typeFilter;
        return matchesSearch && matchesType;
    });

    const groupBy = document.getElementById('groupFilter').value;
    if (groupBy === 'month') {
        const grouped = {};
        filteredPatients.forEach(patient => {
            const date = patient.last_visit || patient.registration_date;
            const monthYear = date ? new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'long' }) : 'No Date';
            if (!grouped[monthYear]) {
                grouped[monthYear] = [];
            }
            grouped[monthYear].push(patient);
        });
        renderPatients(grouped, groupBy);
    } else {
        renderPatients(filteredPatients, groupBy);
    }
}

function renderPatients(patients, groupBy) {
    const tbody = document.getElementById('patientsTableBody');

    if (groupBy === 'month' && typeof patients === 'object') {
        let html = '';
        Object.keys(patients).sort((a, b) => {
            if (a === 'No Date') return 1;
            if (b === 'No Date') return -1;
            return new Date(b + ' 1') - new Date(a + ' 1');
        }).forEach(monthYear => {
            html += `<tr class="group-header"><td colspan="5">${monthYear}</td></tr>`;
            patients[monthYear].forEach(patient => {
                html += generatePatientRow(patient);
            });
        });
        tbody.innerHTML = html;
    } else {
        const patientList = Array.isArray(patients) ? patients : Object.values(patients).flat();
        tbody.innerHTML = patientList.map(generatePatientRow).join('');
    }
}

function generatePatientRow(patient) {
    const contactInfo = [];
    if (patient.email) contactInfo.push(`<div>${patient.email}</div>`);
    if (patient.phone) contactInfo.push(`<div>${patient.phone}</div>`);

    const lastVisit = patient.last_visit ? new Date(patient.last_visit).toLocaleDateString() : 'Never';

    let viewLink = '';
    if (patient.type === 'registered') {
        viewLink = `<a href="patient_details.php?patient_id=${patient.id}" class="btn-view">View Details</a>`;
    } else {
        viewLink = `<a href="patient_details.php?walkin_name=${encodeURIComponent(patient.name)}&walkin_phone=${encodeURIComponent(patient.phone)}" class="btn-view">View Details</a>`;
    }

    return `
        <tr>
            <td><span class="patient-name">${patient.name}</span></td>
            <td><span class="patient-type ${patient.type}">${patient.type}</span></td>
            <td><div class="contact-info">${contactInfo.join('')}</div></td>
            <td><span class="last-visit">${lastVisit}</span></td>
            <td>${viewLink}</td>
        </tr>
    `;
}
</script>

<?php include("../includes/footer.php"); ?>
