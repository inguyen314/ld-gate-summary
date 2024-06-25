document.addEventListener('DOMContentLoaded', function () {
    // Display the loading_alarm_mvs indicator
    const loadingIndicatorGageData = document.getElementById('loading_ld_gate_summary');
    loadingIndicatorGageData.style.display = 'block';

    // Gage control json file URL
    const jsonFileURL = 'https://wm.mvs.ds.usace.army.mil/php_data_api/public/json/gage_control.json';
    console.log('jsonFileURL: ', jsonFileURL);
    
    // Fetch JSON data from the specified URL
    fetch(jsonFileURL)
        .then(response => {
            // Check if response is OK, if not, throw an error
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            // Parse the response as JSON
            return response.json();
        })
        .then(data => {
            // Log the fetched data
            console.log('data: ', data);

            // Filter and sort the data
            const filteredAndSortedData = data.reduce((accumulator, currentValue) => {
                // Extract the 'gages' array
                const gages = currentValue.gages || [];

                // Filter out gages where ld_gate_summay is true
                const filteredGages = gages.filter(gage => gage.ld_gate_summay === true);

                // Push filtered gages to the accumulator
                accumulator.push(...filteredGages);

                return accumulator;
            }, []);

            // Sort filtered data based on ld_gate_summay_sort_order
            filteredAndSortedData.sort((a, b) => {
                const orderA = a.ld_gate_summay_sort_order || 0; // Use 'ld_gate_summay_sort_order' field, default to 0 if missing
                const orderB = b.ld_gate_summay_sort_order || 0;
                return orderA - orderB; // Sort in ascending ld_gate_summay_sort_order
            });

            // Log the filtered and sorted data
            console.log("filteredAndSortedData = ", filteredAndSortedData);

            // Call the function to create and populate the table
			createTable(filteredAndSortedData);
        })
        .catch(error => {
            // Log any errors that occur during fetching or parsing JSON
            console.error('Error fetching data:', error);
        })
        .finally(() => {
            // Hide the loading_alarm_mvs indicator regardless of success or failure
            loadingIndicatorGageData.style.display = 'none';
        });
});



// Function to create ld summary table
function createTable(filteredAndSortedData) {
    // Create a table element
    const table = document.createElement('table');
    table.setAttribute('id', 'gage_data'); // Set the id to "gage_data"

    // Get current data time and minus two hours to compare current value
    const currentDateTime = new Date();
    const currentDateTimeMinusHours = subtractHoursFromDate(currentDateTime, 2);
    console.log('currentDateTime:', currentDateTime);
    console.log('currentDateTimeMinusHours :', currentDateTimeMinusHours);

    // Iterate through the filteredAndSortedData to populate the table
    filteredAndSortedData.forEach((data) => {
        // Create a row for the title
        const titleRow = document.createElement('tr');
        const titleCell = document.createElement('th');
        titleCell.textContent = data.location_id;
        titleCell.colSpan = 6; // Set colspan to 6
        titleCell.style.textAlign = 'left'; // Align text to the left
        titleCell.style.height = '50px';
        titleRow.appendChild(titleCell);

        // Create a table header row
        const headerRow = document.createElement('tr');

        // Create table headers for the desired columns
        const columns = ["Date Time", "Pool", "Tail Water", "Hinge Point", "Tainter", "Roller"];
        columns.forEach((columnName) => {
            const th = document.createElement('th');
            th.textContent = columnName;
            th.style.height = '50px';
            headerRow.appendChild(th);
        });

        // Append the title row to the table
        table.appendChild(titleRow);

        // Append the header row to the table
        table.appendChild(headerRow);

        // Create a new row for each data object
        const row = table.insertRow();
        
        console.log("Calling fetchAndUpdateData")
        fetchAndUpdateData(data.project_id, data.tsid_pool, data.tsid_tw, data.tsid_hinge, data.tsid_taint, data.tsid_roll, currentDateTimeMinusHours, row);
    });

    // Append the table to the document or a specific container
    const tableContainer = document.getElementById('table_container_ld_gate_summary');
    if (tableContainer) {
        tableContainer.appendChild(table);
    }
}



// Function to fetch ld summary data
function fetchAndUpdateData(project_id, tsid_pool, tsid_tw, tsid_hinge, tsid_taint, tsid_roller, currentDateTimeMinusHours, row) {
    // Create an object to hold all the properties you want to pass
    const dataToSend = {
        project_id: project_id,
        pool: tsid_pool,
        tw: tsid_tw,
        hinge: tsid_hinge,
        taint: tsid_taint,
        roll: tsid_roller,
    };

    // Convert the object into a query string
    const queryString = Object.keys(dataToSend).map(key => key + '=' + dataToSend[key]).join('&');

    // Make an AJAX request to the PHP script, passing all the variables
    const url = `https://wm.mvs.ds.usace.army.mil/php_data_api/public/get_ld_gate_summary.php?${queryString}`;
    console.log('url :', url);

    fetch(url)
        .then(response => response.json())
        .then(data => {
            console.log('data :', data);
            // Iterate over each item in the data array
            data.forEach(obj => {
                const newRow = row.parentNode.insertRow(row.rowIndex + 1);

                // DATE TIME
                const dateTimeCell = newRow.insertCell();
                dateTimeCell.innerHTML = obj.date_time;

                // POOL
                const poolCell = newRow.insertCell();
                let poolCellHTML = "";
                if ((parseFloat(obj.pool)).toFixed(2) > 900) {
                    poolCellHTML = "Open River";
                } else if (obj.pool === null) {
                    poolCellHTML = "";
                } else {
                    poolCellHTML = "<span title = '" + obj.pool_cwms_ts_id + "'>" + (parseFloat(obj.pool)).toFixed(2) + "</span>";
                };
                poolCell.innerHTML = poolCellHTML;


                // TAIL WATER
                const tailWaterCell = newRow.insertCell();
                let tailWaterCellHTML = "";
                if ((parseFloat(obj.tw)).toFixed(2) > 900) {
                    tailWaterCellHTML = "Open River";
                } else if (obj.tw === null) {
                    tailWaterCellHTML = "-M-";
                } else {
                    tailWaterCellHTML = "<span title = '" + obj.tw_cwms_ts_id + "'>" + (parseFloat(obj.tw)).toFixed(2) + "</span>";
                };
                tailWaterCell.innerHTML = tailWaterCellHTML;


                // HINGE
                const hingeCell = newRow.insertCell();
                let hingeCellHTML = "";
                if (obj.hinge === null) {
                    hingeCellHTML = "";
                } else {
                    hingeCellHTML = "<span title = '" + obj.hinge_cwms_ts_id + "'>" + (parseFloat(obj.hinge)).toFixed(2) + "</span>";
                };
                hingeCell.innerHTML = hingeCellHTML;


                // TAINTER
                const tainterCell = newRow.insertCell();
                let tainterCellHTML = "";
                if ((parseFloat(obj.taint)).toFixed(2) > 900) {
                    tainterCellHTML = "Open River";
                } else if (obj.taint === null) {
                    tainterCellHTML = "";
                } else {
                    tainterCellHTML = "<span title = '" + obj.taint_cwms_ts_id + "'>" + (parseFloat(obj.taint)).toFixed(2) + "</span>";
                };
                tainterCell.innerHTML = tainterCellHTML;

                // ROLLER
                const rollerCell = newRow.insertCell();
                let rollerCellHTML = "";
                if ((parseFloat(obj.roll)).toFixed(2) > 900) {
                    rollerCellHTML = "Open River";
                } else if (obj.roll === null) {
                    rollerCellHTML = "";
                } else {
                    rollerCellHTML = "<span title = '" + obj.roll_cwms_ts_id + "'>" + (parseFloat(obj.roll)).toFixed(2) + "</span>";
                }
                rollerCell.innerHTML = rollerCellHTML;
            });
        })
        .catch(error => {
            console.error('Error:', error);
            // Handle errors here
        });
}



// Function to get current data time
function subtractHoursFromDate(date, hoursToSubtract) {
    return new Date(date.getTime() - (hoursToSubtract * 60 * 60 * 1000));
}