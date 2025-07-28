function openTab(evt, tabName, data = null) {
    var i, tabcontent, tablinks, subcontent;
    
    // Hide all tab content
    tabcontent = document.getElementsByClassName("tabcontent");
    for (i = 0; i < tabcontent.length; i++) {
        tabcontent[i].style.display = "none";
    }

    // Hide all sub-bar content
    subcontent = document.getElementsByClassName("subcontent");
    for (i = 0; i < subcontent.length; i++) {
        subcontent[i].style.display = "none";
    }

    // Remove active class from all tab buttons
    tablinks = document.getElementsByClassName("tablinks");
    for (i = 0; i < tablinks.length; i++) {
        tablinks[i].className = tablinks[i].className.replace(" active", "");
    }

    // Show the clicked tab's content and corresponding sub-bar content
    document.getElementById(tabName).style.display = "block";
    document.getElementById('subbar-' + tabName).style.display = "block";

    // Add active class to the clicked tab button if evt is present
    if (evt && evt.currentTarget) {
        evt.currentTarget.className += " active";
    }
    
    if (data) {
        handleDataForTab(tabName, data);
    }
    
        // Call buildSmallBoxes when Tab1 is opened
    if (tabName === 'Tab1') {
        buildSmallBoxes();
    }
    
    if (tabName === 'Tab2') {
        document.getElementById('member-select').value = "";
        document.getElementById('calling-select').value = "";
        document.getElementById('member-callings-container').innerHTML = "";
        document.getElementById('calling-members-container').innerHTML = "";
        
    }    
}

// Load default tab on page load
document.addEventListener('DOMContentLoaded', function () {
    document.querySelector('.tablinks').click(); // Simulate click on first tab to load content
});

document.addEventListener('DOMContentLoaded', function() {
    // Select all toggle buttons in the sub-bar for Tab1
    document.querySelectorAll('#subbar-Tab1 .toggle-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            // Get the organization from the button's class or a data attribute
            // Example: use a data-org attribute for clarity
            const org = btn.getAttribute('data-org');
            if (org) {
                toggleVisibility(org, btn);
            }
        });
    });
});


function handleDataForTab(tabName, data) {

    if (tabName === 'Tab2') {
        // Use data in Tab2 as needed, like filling a form or selecting an option
        document.getElementById('member-select').value = data.memberId || '';
        fetchCurrentCallings(data.memberId);
        document.getElementById('calling-select').value = data.callingId || '';
        fetchCallingMembers(data.callingId);
    }
}







// Toggles the visibility of the small boxes for each calling
function toggleVisibility(className, button) {
    const elements = document.getElementsByClassName(className);
    let isHidden = false;

    for (let i = 0; i < elements.length; i++) {
        const element = elements[i];
        const currentDisplay = window.getComputedStyle(element).display;
        
        if (currentDisplay === "none") {
            element.style.display = "block"; // Show the element if it's hidden
        } else {
            element.style.display = "none"; // Hide the element if it's visible
            isHidden = true; // Mark that at least one element was hidden
        }
    }

    // Toggle button style based on visibility
    if (isHidden) {
        button.classList.add('hidden'); // Add class to style button when boxes are hidden
    } else {
        button.classList.remove('hidden'); // Remove class when boxes are shown
    }
}


// Function to hide all small boxes
function clearAll() {
    const allBoxes = document.querySelectorAll('.small-box');
    
    allBoxes.forEach(box => {
        box.style.display = 'none';
    });

    // Update all buttons to reflect hidden state
    const buttons = document.querySelectorAll('.toggle-btn');
    buttons.forEach(button => {
        button.classList.add('hidden'); // Mark all buttons as hidden
    });
}

// Function to show all small boxes
function showAll() {
    const allBoxes = document.querySelectorAll('.small-box');

    allBoxes.forEach(box => {
        box.style.display = 'block';
    });

    // Update all buttons to reflect shown state
    const buttons = document.querySelectorAll('.toggle-btn');
    buttons.forEach(button => {
        button.classList.remove('hidden'); // Mark all buttons as visible
    });
}


let currentDropdownEventListener = null; // Track the current event listener
let allCandidates = []; // Store all candidates initially

function showPopup(title, callingId) {
    // Set the modal title
    document.getElementById('popup-title').textContent = title + " Candidates";
    const popupTitle = document.getElementById("popup-title"); // or use querySelector if you need
    popupTitle.setAttribute("data-calling-id", callingId);
    
    // Reset filter dropdowns to default
    document.getElementById('gender-filter').value = "";  // Resets to 'All'
    document.getElementById('age-filter').value = "";     // Resets to 'All'    

    fetch('get_calling_data_for_popup.php')
        .then(response => response.json())
        .then(data => {
            const dropdown = document.getElementById('callingDropdown');
            dropdown.innerHTML = '<option value="">Select a Candidate</option>'; // Clear dropdown

            // Store all data for filtering
            allCandidates = data;

            // Populate dropdown initially with all candidates
            populateCandidateDropdown(allCandidates);

            // Remove previous event listener if any
            if (currentDropdownEventListener) {
                dropdown.removeEventListener('change', currentDropdownEventListener);
            }

            // Define the new event listener function
            currentDropdownEventListener = function () {
                const selectedMemberId = dropdown.value;
                if (selectedMemberId) {
                    addToPossibleCallings(selectedMemberId, callingId);
                }
            };

            // Attach the new event listener
            dropdown.addEventListener('change', currentDropdownEventListener);

        })
        .catch(error => {
            console.error('Error fetching data:', error);
        });
    loadCallingComments(callingId);
    loadSelectionList(callingId);

    // Display the overlay and modal
    document.getElementById('popup-overlay').style.display = 'block';
    document.getElementById('popup-modal').style.display = 'block';
}

// load the comments about callings
function loadCallingComments(callingId) {
    // Ensure callingId is provided
    if (!callingId) {
        console.error("No calling ID provided.");
        return;
    }

    // Fetch data from the PHP file using POST
    fetch("load_calling_comments.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded"
        },
        body: `calling_id=${encodeURIComponent(callingId)}`
    })
    .then(response => {
        if (!response.ok) {
            throw new Error("Network response was not ok " + response.statusText);
        }
        return response.text();  // Parse the response as text
    })
    .then(data => {
        // Display the fetched data in the element with ID 'candidate-notes'
        document.getElementById('candidate-notes').value = data; // Use .value for textarea
    })
    .catch(error => {
        console.error("There was a problem with the fetch operation:", error);
    });
}



function saveCallingComments(callingId) {
    // Get the comments from the textarea
    const comments = document.getElementById('candidate-notes').value;

    // Ensure callingId and comments are provided
    if (!callingId || comments === undefined) {
        console.error("Calling ID or comments not provided.");
        return;
    }

    // Send the data to the PHP file using POST
    fetch("save_calling_comments.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded"
        },
        body: `calling_id=${encodeURIComponent(callingId)}&comments=${encodeURIComponent(comments)}`
    })
    .then(response => {
        if (!response.ok) {
            throw new Error("Network response was not ok " + response.statusText);
        }
        return response.text();
    })
    .then(data => {
        // Handle success or error message from PHP
        console.log(data); // Display a success message or error returned by PHP
    })
    .catch(error => {
        console.error("There was a problem with the fetch operation:", error);
    });
}




// Function to populate the candidates in the dropdown
function populateCandidateDropdown(data) {
    const dropdown = document.getElementById('callingDropdown');
    dropdown.innerHTML = '<option value="">Select a Candidate</option>';

    data.forEach(item => {
        const option = document.createElement('option');
        option.value = JSON.stringify(item);
        option.value = item.member_id; // Set value to member_id for easier reference
        option.textContent = `${item['First Name']} ${item['Last Name']} - ${item['callings_info']}`;
        dropdown.appendChild(option);
    });
}

// Function to apply filters based on gender and age
function applyFilters() {
    const gender = document.getElementById('gender-filter').value;
    const ageGroup = document.getElementById('age-filter').value;

    const filteredCandidates = allCandidates.filter(candidate => {
        let matchesGender = true;
        let matchesAge = true;

        // Check gender filter
        if (gender) {
            matchesGender = candidate.gender === gender;
        }

        // Check age filter
        if (ageGroup) {
            const age = calculateAge(candidate.birthdate); // Calculate age from birthdate
            matchesAge = ageGroup === 'under_18' ? age < 18 : age >= 18;
        }

        return matchesGender && matchesAge;
    });

    // Update the dropdown with filtered candidates
    populateCandidateDropdown(filteredCandidates);
}

// Helper function to calculate age from birthdate
function calculateAge(birthdate) {
    const birthDate = new Date(birthdate);
    const today = new Date();
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
        age--;
    }
    return age;
}

function closePopup() {
    const popupTitle = document.getElementById("popup-title");
    const dropdown = document.getElementById('callingDropdown');
    const callingId = popupTitle.getAttribute("data-calling-id");

    // Remove the event listener when the modal closes
    if (currentDropdownEventListener) {
        dropdown.removeEventListener('change', currentDropdownEventListener);
        currentDropdownEventListener = null; // Reset the listener reference
    }

    document.getElementById('popup-overlay').style.display = 'none';
    document.getElementById('popup-modal').style.display = 'none';
    
    saveCallingComments(callingId);
}

// Function to add member to possible callings
function addToPossibleCallings(memberId, callingId) {
    fetch('add_possible_calling.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `member_id=${encodeURIComponent(memberId)}&calling_id=${encodeURIComponent(callingId)}`
    })
    .then(response => response.text())
    .then(data => {
        loadSelectionList(callingId); // Reload the selection list after adding
    })
    .catch(error => {
        console.error('Error adding to database:', error);
    });
}






function loadSelectionList(newCalling) {
    fetch(`get_possible_callings.php?newCalling=${encodeURIComponent(newCalling)}`)
        .then(response => response.json())
        .then(data => {
            const selectionList = document.getElementById('selectionList');
            selectionList.innerHTML = ''; // Clear existing list
            
            if (data.length === 0) {
                const noResults = document.createElement('div');
                noResults.classList.add('no-results');
                noResults.textContent = 'No candidates are currently under consideration for this calling.';
                selectionList.appendChild(noResults);
                return;
            }

            data.forEach(item => {
                const selectedItem = document.createElement('div');
                selectedItem.classList.add('selected-item');
                
                // Set possible_callings_id as a data attribute
                selectedItem.setAttribute('data-possible-callings-id', item['Possible Callings ID']);
                selectedItem.setAttribute('data-member-id', item['Member ID']);
                
                // Create a "delete" button (the "x")
                const deleteButton = document.createElement('span');
                deleteButton.textContent = 'x';
                deleteButton.classList.add('delete-btn');
                deleteButton.style.cursor = 'pointer';
                deleteButton.style.marginRight = '10px';

                // Add click event listener to delete the entry
                deleteButton.addEventListener('click', function () {
                    removeFromConsideration(item['Possible Callings ID'], item['Calling ID']);
                });

                // Create a green checkmark button
                const checkButton = document.createElement('span');
                checkButton.textContent = '✔';
                checkButton.classList.add('check-btn');
                checkButton.style.cursor = 'pointer';
                checkButton.style.color = 'green';
                checkButton.style.marginLeft = '10px';

                // Add click event listener for the checkmark action
                checkButton.addEventListener('click', function () {
                    // Placeholder for the function you want to define later
                    confirmSelection(item['Member ID'], item['Calling ID']);
                });

                // Add the delete button, text, and checkmark button
                selectedItem.appendChild(deleteButton);
                selectedItem.append(`${item['First Name']} ${item['Last Name']} - ${item['callings_info']}`);
                selectedItem.appendChild(checkButton);

                selectionList.appendChild(selectedItem);
            });
        })
        .catch(error => {
            console.error('Error fetching possible callings:', error);
        });
}

//function to handle the green checkmarks
function confirmSelection(memberId, callingId) {
    closePopup();
    openTab(null, 'Tab2', { memberId, callingId });
}



// New function to update the status in the database
function removeFromConsideration(possibleCallingsId, callingId) {
    fetch('remove_from_consideration.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `possible_callings_id=${encodeURIComponent(possibleCallingsId)}`
    })
    .then(response => response.text())
    .then(data => {
        console.log(data); // Optional: Display success message or handle response

        // Refresh the selection list to show updated data
        loadSelectionList(callingId);
    })
    .catch(error => {
        console.error('Error updating status:', error);
    });
}




function buildSmallBoxes() {
    const largeBox = document.getElementById('large-box');
    largeBox.innerHTML = '<p>Loading callings...</p>';

    fetch('get_all_overview_data.php')
        .then(response => {
            if (!response.ok) throw new Error('Failed to load callings overview');
            return response.json();
        })
        .then(data => {
            // --- Step 1: Process the data into the correct grouped structure ---
            const groupedData = {};

            for (const callingId in data) {
                const callingInfo = data[callingId];
                const details = callingInfo.details;
                const groupingName = details.grouping;
                const organizationName = details.organization; // Get the organization name

                // If this is the first time we see this grouping, create the group object
                if (!groupedData[groupingName]) {
                    groupedData[groupingName] = {
                        organization: organizationName, // <<< Store the organization here
                        callings: []                  // <<< Initialize an empty array for callings
                    };
                }

                // Add the current calling to the 'callings' array within its group
                groupedData[groupingName].callings.push({
                    callingId: callingId,
                    callingName: details.calling_name,
                    isConsidered: details.is_considered,
                    priority: details.priority,
                    members: callingInfo.members
                });
            }

            // --- Step 2: Sort callings within each group by priority ---
            for (const groupingName in groupedData) {
                // Access the 'callings' array inside the group object to sort it
                groupedData[groupingName].callings.sort((a, b) => {
                    return a.priority - b.priority;
                });
            }
            
            // --- Step 3: Render the HTML from the sorted, grouped structure ---
            largeBox.innerHTML = ''; // Clear loading message

            for (const groupingName in groupedData) {
                // Get the entire group object, which contains 'organization' and 'callings'
                const groupInfo = groupedData[groupingName]; 
                
                // Get the array of callings from the group object
                const callingsInGroup = groupInfo.callings; 

                // The rest of your mapping logic is perfect and doesn't need to change
                let groupContentHtml = callingsInGroup.map(calling => {
                    const membersHtml = calling.members.map(member =>
                        `<div data-member-id="${member.member_id}">     - ${member.first_name} ${member.last_name} (${member.date_set_apart})</div>`
                    ).join('') || `<div style="font-style: italic;color: red;">     - (Vacant)</div>`;

                    const deltaSymbolHtml = calling.isConsidered ? '<span class="delta-symbol">▲</span>' : '';

                    return `
                        <div class="box-title" data-calling-id="${calling.callingId}" data-title="${calling.callingName}">
                            ${deltaSymbolHtml} ${calling.callingName}
                        </div>
                        <div class="box-content">
                            ${membersHtml}
                        </div>
                    `;
                }).join('');

                // Now this line will work because groupInfo.organization is correctly defined
                const orgClassName = groupInfo.organization.replace(/[^a-zA-Z0-9]/g, '');

                const boxHtml = `
                    <div class="small-box ${orgClassName}">
                        <div class="box-header">${groupingName}</div>
                        <div class="box-content-wrapper" style="padding: 10px;">
                            ${groupContentHtml}
                        </div>
                    </div>
                `;

                largeBox.insertAdjacentHTML('beforeend', boxHtml);
            }

            // --- Step 4: Attach event listeners ---
            attachPopupListeners();
        })
        .catch(error => {
            console.error('Error loading callings overview:', error);
            largeBox.innerHTML = `<p style="color: red;">Error loading data. Please try again. (${error.message})</p>`;
        });
}

// This function remains the same and works perfectly.
function attachPopupListeners() {
    const titles = document.querySelectorAll('.box-title');
    titles.forEach(title => {
        title.addEventListener('click', function () {
            const titleText = title.getAttribute('data-title');
            const callingId = title.getAttribute('data-calling-id');
            showPopup(titleText, callingId);
        });
    });
}



// Fills the calling data with member information for each small box
function loadData(callingId, targetElementId) {
    fetch('fetch_calling_data.php?calling_id=' + encodeURIComponent(callingId))
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            const targetElement = document.getElementById(targetElementId);
            targetElement.innerHTML = ''; // Clear existing content

            data.forEach(item => {
                const firstName = item['First Name'];
                const lastName = item['Last Name'];
                const dateSetApart = item['Date Set Apart'];
                const memberId = item['Member ID'];

                // Create a new div for each entry with data-member-id
                const entryDiv = document.createElement('div');
                entryDiv.textContent = `${firstName} ${lastName} (${dateSetApart})`;
                entryDiv.setAttribute('data-member-id', memberId); // Add data-member-id

                targetElement.appendChild(entryDiv);
            });
        })
        .catch(error => {
            console.error('There was a problem with the fetch operation:', error);
            document.getElementById(targetElementId).innerHTML = 'Error loading data.';
        });
}






document.addEventListener('DOMContentLoaded', function() {
    // Function to populate member dropdown
    function populateMembers() {
        fetch('get_members.php')
            .then(response => response.json())
            .then(data => {
                const memberSelect = document.getElementById('member-select');
                data.forEach(member => {
                    const option = document.createElement('option');
                    option.value = member.member_id;
                    option.textContent = `${member.first_name} ${member.last_name}`;
                    memberSelect.appendChild(option);
                });
            })
            .catch(error => console.error('Error fetching members:', error));
    }

    // Function to populate callings dropdown
    function populateCallings() {
        fetch('get_callings.php')
            .then(response => response.json())
            .then(data => {
                const callingSelect = document.getElementById('calling-select');
                data.forEach(calling => {
                    const option = document.createElement('option');
                    option.value = calling.calling_id;
                    option.textContent = calling.calling_name;
                    callingSelect.appendChild(option);
                });
            })
            .catch(error => console.error('Error fetching callings:', error));
    }

    // Populate member and calling dropdowns as before
    populateMembers();
    populateCallings();
    
    // Set the default date for the Date Set Apart field
    const dateSetApartField = document.getElementById('date-set-apart');
    const today = new Date().toISOString().substr(0, 10); // Get today's date in YYYY-MM-DD format
    dateSetApartField.value = today;

    // Listen for member selection changes to check for current callings
    document.getElementById('member-select').addEventListener('change', function() {
        const memberId = this.value;

        if (memberId) {
            fetchCurrentCallings(memberId);
        } else {
            document.getElementById('member-callings-container').innerHTML = ''; // Clear the table if no member is selected
        }
    });
    
    


    // Handle "Assign Calling" form submission
    document.getElementById('calling-form').addEventListener('submit', function(event) {
        event.preventDefault();

        const formData = new FormData(this);

        const releaseCallings = [];
        document.querySelectorAll('.release-calling-checkbox:checked, .release-member-checkbox:checked').forEach(checkbox => {
            releaseCallings.push(checkbox.value);
        });

        formData.append('release_callings', JSON.stringify(releaseCallings));

        fetch('add_calling.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            document.getElementById('form-response').textContent = data;
            document.getElementById('calling-form').reset();
            dateSetApartField.value = today;
            document.getElementById('member-callings-container').innerHTML = '';
            document.getElementById('calling-members-container').innerHTML = '';
        })
        .catch(error => {
            console.error('Error submitting form:', error);
        });
    });

    // Handle "Release Only" button click
    document.getElementById('release-only-btn').addEventListener('click', function() {
        const releaseCallings = [];
    
        // Collect checked callings from both tables
        document.querySelectorAll('.release-calling-checkbox:checked, .release-member-checkbox:checked').forEach(checkbox => {
            releaseCallings.push(checkbox.value);
        });
    
        if (releaseCallings.length === 0) {
            alert('No callings selected for release.');
            return;
        }
    
        // --- NEW: Get the date from the form input ---
        const releaseDate = document.getElementById('date-set-apart').value;
    
        // --- NEW: Add validation to ensure a date is selected ---
        if (!releaseDate) {
            alert('Please select a release date.');
            return; // Stop execution if the date is missing
        }
    
        // Send an AJAX request to release the selected callings
        const formData = new FormData();
        formData.append('release_callings', JSON.stringify(releaseCallings));
        formData.append('release_date', releaseDate); // --- NEW: Add the date to the form data ---
    
        fetch('release_callings.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            document.getElementById('form-response').textContent = data;
            
            // Refresh the calling lists to show the changes
            const memberId = document.getElementById('member-select').value;
            if (memberId) fetchCurrentCallings(memberId);
            
            const callingId = document.getElementById('calling-select').value;
            if (callingId) fetchCallingMembers(callingId);
    
        })
        .catch(error => {
            console.error('Error releasing callings:', error);
            document.getElementById('form-response').textContent = 'An error occurred while releasing callings.';
        });
    });

});

// Fetch and display current callings of the selected member
function fetchCurrentCallings(memberId) {
    fetch(`get_member_callings.php?member_id=${encodeURIComponent(memberId)}`)
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('member-callings-container');
            container.innerHTML = ''; // Clear previous content

            if (data.length > 0) {
                let table = '<table>';
                table += `
                    <tr>
                        <th>Status</th>
                        <th>Release?</th>
                        <th>Calling Name</th>
                        <th>Date Started</th>
                        <th>Date Released</th>
                    </tr>`;

                data.forEach(calling => {
                    const isActive = !calling.date_released; // Active if no release date

                    table += `
                        <tr>
                            <td>${isActive ? 'Active' : 'Old'}</td>
                            <td>${isActive ? `<input type="checkbox" class="release-calling-checkbox" value="${calling.id}">` : ''}</td>
                            <td>${calling.calling_name}</td>
                            <td>${calling.date_set_apart}</td>
                            <td>${calling.date_released ? calling.date_released : '—'}</td>
                        </tr>
                    `;
                });

                table += '</table>';
                container.innerHTML = table;
            } else {
                container.innerHTML = '<p>No current or past callings for this member.</p>';
            }
        })
        .catch(error => {
            console.error('Error fetching current callings:', error);
        });
}


function fetchCallingsForMember(memberId) {
    fetch(`get_member_callings.php?member_id=${encodeURIComponent(memberId)}`)
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('member-callings');
            container.innerHTML = ''; // Clear previous content

            if (data.length > 0) {
                let heading = '<label>Calling History:</label>'; // Add your heading here

                let table = '<table>';
                table += `
                    <tr>
                        <th>Status</th>
                        <th>Calling Name</th>
                        <th>Date Started</th>
                        <th>Date Released</th>
                    </tr>`;

                data.forEach(calling => {
                    const isActive = !calling.date_released; // Active if no release date

                    table += `
                        <tr>
                            <td>${isActive ? 'Active' : 'Old'}</td>
                            <td>${calling.calling_name}</td>
                            <td>${calling.date_set_apart}</td>
                            <td>${calling.date_released ? calling.date_released : '—'}</td>
                        </tr>
                    `;
                });

                table += '</table>';
                container.innerHTML = heading + table;
            } else {
                container.innerHTML = '<p>No current or past callings for this member.</p>';
            }
        })
        .catch(error => {
            console.error('Error fetching current callings:', error);
        });
}

function fetchPossibleCallingsForMember(memberId) {
    fetch(`get_considered_callings.php?member_id=${encodeURIComponent(memberId)}`)
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('member-callings-considered');
            container.innerHTML = ''; // Clear previous content

            if (data.length > 0) {
                let heading = '<label>Callings Considered:</label>';
                let table = '<table>';
                table += `
                    <tr>
                        <th>Status</th>
                        <th>Calling Name</th>
                        <th>Date Updated</th>
                    </tr>`;

                data.forEach(calling => {
                    table += `
                        <tr>
                            <td>${calling.status}</td>
                            <td>${calling.calling_name}</td>
                            <td>${calling.date_updated}</td>
                        </tr>
                    `;
                });

                table += '</table>';
                container.innerHTML = heading + table; // Insert the heading before the table
            } else {
                container.innerHTML = '<p>No callings currently being considered for this member.</p>';
            }
        })
        .catch(error => {
            console.error('Error fetching possible callings:', error);
        });
}




// Listen for calling selection changes to check for current members
document.getElementById('calling-select').addEventListener('change', function() {
    const callingId = this.value;

    if (callingId) {
        fetchCallingMembers(callingId);
    } else {
        document.getElementById('calling-members-container').innerHTML = ''; // Clear the table if no calling is selected
    }
});

// Fetch and display members associated with the selected calling
function fetchCallingMembers(callingId) {
    fetch(`get_calling_members.php?calling_id=${encodeURIComponent(callingId)}`)
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('calling-members-container');
            container.innerHTML = ''; // Clear previous content

            if (data.length > 0) {
                let table = '<table>';
                table += `
                    <tr>
                        <th>Status</th>
                        <th>Release?</th>
                        <th>Member Name</th>
                        <th>Date Started</th>
                        <th>Date Released</th>
                    </tr>`;

                data.forEach(member => {
                    const isActive = !member.date_released; // Active if no release date

                    table += `
                        <tr>
                            <td>${isActive ? 'Active' : 'Old'}</td>
                            <td>${isActive ? `<input type="checkbox" class="release-member-checkbox" value="${member.id}">` : ''}</td>
                            <td data-member-id="${member.member_id}" data-current-calling-id="${member.id}">${member.member_name}</td>
                            <td>${member.date_set_apart}</td>
                            <td>${member.date_released ? member.date_released : '—'}</td>
                        </tr>
                    `;
                });

                table += '</table>';
                container.innerHTML = table;
            } else {
                container.innerHTML = '<p>No members found for this calling.</p>';
            }
        })
        .catch(error => {
            console.error('Error fetching calling members:', error);
        });
}

function fetchMembersForCalling(callingId) {
    fetch(`get_calling_members.php?calling_id=${encodeURIComponent(callingId)}`)
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('callings-for-member');
            container.innerHTML = ''; // Clear previous content

            if (data.length > 0) {
                let table = '<table>';
                let heading = '<label>Members with this calling:</label>';
                table += `
                    <tr>
                        <th>Status</th>

                        <th>Member Name</th>
                        <th>Date Started</th>
                        <th>Date Released</th>
                    </tr>`;

                data.forEach(member => {
                    const isActive = !member.date_released; // Active if no release date

                    table += `
                        <tr>
                            <td>${isActive ? 'Active' : 'Old'}</td>

                            <td>${member.member_name}</td>
                            <td>${member.date_set_apart}</td>
                            <td>${member.date_released ? member.date_released : '—'}</td>
                        </tr>
                    `;
                });

                table += '</table>';
                container.innerHTML = heading + table;
            } else {
                container.innerHTML = '<p>No members found for this calling.</p>';
            }
        })
        .catch(error => {
            console.error('Error fetching calling members:', error);
        });
}

function fetchPossibleMembersForCalling(callingId) {
    fetch(`get_possible_members.php?calling_id=${encodeURIComponent(callingId)}`)
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('calling-members-considered');
            container.innerHTML = ''; // Clear previous content

            if (data.length > 0) {
                let heading = '<label>Members considered for this calling:</label>';
                let table = '<table>';
                table += `
                    <tr>
                        <th>Status</th>
                        <th>Member Name</th>
                        <th>Date Updated</th>
                    </tr>`;

                data.forEach(member => {
                    table += `
                        <tr>
                            <td>${member.status}</td>
                            <td>${member.member_name}</td>
                            <td>${member.date_updated}</td>
                        </tr>
                    `;
                });

                table += '</table>';
                container.innerHTML = heading + table; // Insert the heading before the table
            } else {
                container.innerHTML = '<p>No members currently being considered for this calling.</p>';
            }
        })
        .catch(error => {
            console.error('Error fetching possible members:', error);
        });
}



document.addEventListener('DOMContentLoaded', function () {
    // Event listeners for Members and Callings buttons
    document.getElementById('members-btn').addEventListener('click', function () {
        loadMembersForm();
        underlineButton('members-btn');
    });

    document.getElementById('callings-btn').addEventListener('click', function () {
        loadCallingsForm();
        underlineButton('callings-btn');
    });
});

// Function to underline the active button
function underlineButton(buttonId) {
    document.querySelectorAll('.toggle-btn').forEach(btn => btn.classList.remove('active'));
    document.getElementById(buttonId).classList.add('active');
}

// Function to load the Members form
function loadMembersForm() {
    const container = document.getElementById('information-container');
    container.innerHTML = `
        
        <form id="member-form">
            <h2 class="form-title">Member Information</h2>
            
            <label for="member-form-select">Select Member:</label>
            <select id="member-form-select">
                <option value="">Select a Member</option>
            </select>
            <button id="add-member-btn" type="button" onclick="openAddMemberModal()">Add New Member</button>
            <div id="member-details">

                <label>Member ID:</label>
                <input type="text" id="member-id" disabled>
                
                <label>First Name:</label>
                <input type="text" id="first-name" disabled>
        
                <label>Last Name:</label>
                <input type="text" id="last-name" disabled>
        
                <label>Gender:</label>
                <input type="text" id="gender" disabled>
        
                <label>Birthdate:</label>
                <input type="date" id="birthdate" disabled>
            </div>
            <button type="button" id="edit-btn">Edit</button>
            <button type="button" id="remove-member-btn">Remove</button>
            <button type="button" id="save-btn" style="display: none;">Save</button>
            <button type="button" id="cancel-btn" style="display: none;">Cancel</button>
            <div id="member-form-message" style="margin-top: 10px;"></div>
             <div id="form-response"></div>
            <div id="member-callings"></div> 
            <div id="member-callings-considered"></div>
        </form>
    `;

    
    // Now add the event listeners for the Edit, Save, and Cancel buttons
    const editButton = document.getElementById('edit-btn');
    const saveButton = document.getElementById('save-btn');
    const cancelButton = document.getElementById('cancel-btn');
    const removeButton = document.getElementById('remove-member-btn');
    const formResponse = document.getElementById('form-response');
    const memberSelect = document.getElementById('member-form-select');
    const fields = ['member-id', 'first-name', 'last-name', 'gender', 'birthdate'];
    let originalValues = {};

    // Edit button event listener
    editButton.addEventListener('click', () => {
        fields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            field.disabled = false;
            originalValues[fieldId] = field.value; // Store original values
        });
        editButton.style.display = 'none';
        removeButton.style.display = 'none';
        saveButton.style.display = 'inline-block';
        cancelButton.style.display = 'inline-block';
    });
    
    removeButton.addEventListener('click', () => {
        const memberSelect = document.getElementById('member-form-select');
        const memberId = memberSelect.value;
        const messageContainer = document.getElementById('member-form-message');
    
        if (!memberId) {
            messageContainer.textContent = "Please select a member to remove.";
            return;
        }
    
        const confirmation = confirm("Are you sure you want to remove this member?");
        if (!confirmation) return;
    
        fetch('remove_member.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `member_id=${encodeURIComponent(memberId)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                messageContainer.textContent = "Member removed successfully.";
                document.getElementById('member-form').reset(); // Clear form fields
                memberSelect.remove(memberSelect.selectedIndex); // Remove the deleted member from dropdown
            } else {
                messageContainer.textContent = "Error: " + data.message;
            }
        })
        .catch(error => {
            console.error("Error removing member:", error);
            messageContainer.textContent = "An error occurred while removing the member.";
        });
    });

    // Save button event listener
    saveButton.addEventListener('click', () => {
        const updatedData = {};
        const messageContainer = document.getElementById('member-form-message');
        fields.forEach(fieldId => {
            updatedData[fieldId] = document.getElementById(fieldId).value;
        });
        console.log(updatedData);
        fetch('update_member.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(updatedData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                messageContainer.textContent = 'Member updated successfully.';
            } else {
                messageContainer.textContent = 'Failed to update member.';
            }
            resetForm();
        })
        .catch(error => {
            console.error('Error:', error);
            messageContainer.textContent = 'An error occurred. Please try again.';
            resetForm();
        });
    });

    // Cancel button event listener
    cancelButton.addEventListener('click', () => {
        fields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            field.value = originalValues[fieldId]; // Revert to original values
            field.disabled = true;
        });
        editButton.style.display = 'inline-block';
        removeButton.style.display = 'inline-block';
        saveButton.style.display = 'none';
        cancelButton.style.display = 'none';
        memberSelect.selectedIndex = 0;
    });
    
    function resetForm() {
        fields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            field.disabled = true;
            field.value = ""; // Restore original values
        });
        editButton.style.display = 'inline-block';
        removeButton.style.display = 'inline-block';
        saveButton.style.display = 'none';
        cancelButton.style.display = 'none';
        memberSelect.selectedIndex = 0;

    }
    
    
    
    fetchMembers(); // Populate dropdown
}



// Function to load the Callings form
function loadCallingsForm() {
    const container = document.getElementById('information-container');
    container.innerHTML = `
        
        <form id="calling-form">
            <h2 class="form-title">Calling Information</h2>
            <label for="calling-form-select">Select Calling:</label>
            <select id="calling-form-select">
                <option value="">Select a Calling</option>
            </select>
            <button id="add-calling-btn" type="button" onclick="openAddCallingModal()">Add New Calling</button>
            <div id="calling-details">
                                
                <label>Calling ID:</label>
                <input type="text" id="calling-id" disabled>
                
                <label>Calling Name:</label>
                <input type="text" id="calling-name" disabled>
        
                <label>Organization:</label>
                <select id="organization" disabled>
                    <option value="Aaronic Priesthood Quorums">Aaronic Priesthood Quorums</option>
                    <option value="Additional Callings">Additional Callings</option>
                    <option value="Bishopric">Bishopric</option>
                    <option value="Elders Quorum">Elders Quorum</option>
                    <option value="Facilities">Facilities</option>
                    <option value="Full-Time Missionaries">Full-Time Missionaries</option>
                    <option value="History">History</option>
                    <option value="Music">Music</option>
                    <option value="Primary">Primary</option>
                    <option value="Relief Society">Relief Society</option>
                    <option value="Stake">Stake</option>
                    <option value="Sunday School">Sunday School</option>
                    <option value="Technology">Technology</option>
                    <option value="Temple and Family History">Temple and Family History</option>
                    <option value="Ward Missionaries">Ward Missionaries</option>
                    <option value="Welfare and Self-Reliance">Welfare and Self-Reliance</option>
                    <option value="Young Women">Young Women</option>
                </select>
                <label>Grouping:</label>
                <select id="grouping" disabled>
                    <option value="AP Advisors">AP Advisors</option>
                    <option value="AP Class Presidency">AP Class Presidency</option>
                    <option value="Additional Callings">Additional Callings</option>
                    <option value="Bishopric">Bishopric</option>
                    <option value="Elders Quorum">Elders Quorum</option>
                    <option value="Full-Time Missionaries">Full-Time Missionaries</option>
                    <option value="Music">Music</option>
                    <option value="Primary Teachers">Primary Teachers</option>
                    <option value="Primary Other">Primary Other</option>
                    <option value="Primary Presidency">Primary Presidency</option>
                    <option value="RS Other">RS Other</option>
                    <option value="RS Presidency">RS Presidency</option>
                    <option value="Stake">Stake</option>
                    <option value="SS Teachers">SS Teachers</option>
                    <option value="SS Other">SS Other</option>
                    <option value="SS Presidency">SS Presidency</option>
                    <option value="Work of Salvation">Work of Salvation</option>
                    <option value="YW Class Presidency">YW Class Presidency</option>
                    <option value="YW Presidency">YW Presidency</option>
                </select>
                <label>Priority:</label>
                <input type="text" id="priority" disabled>
            </div>
                <button type="button" id="edit-calling-btn">Edit</button>
                <button type="button" id="remove-calling-btn">Remove</button>
                <button type="button" id="save-calling-btn" style="display: none;">Save</button>
                <button type="button" id="cancel-calling-btn" style="display: none;">Cancel</button>
                <div id="calling-form-message" style="margin-top: 10px;"></div>
                <div id="callings-for-member"></div>
                <div id="calling-members-considered"></div>
            
        </form>
    `;
    
        // Now add the event listeners for the Edit, Save, and Cancel buttons
    const editButton = document.getElementById('edit-calling-btn');
    const saveButton = document.getElementById('save-calling-btn');
    const cancelButton = document.getElementById('cancel-calling-btn');
    const removeButton = document.getElementById('remove-calling-btn');
    const formResponse = document.getElementById('form-response');
    const callingSelect = document.getElementById('calling-form-select');
    const fields = ['calling-id', 'calling-name', 'organization', 'grouping', 'priority'];
    let originalValues = {};

    // Edit button event listener
    editButton.addEventListener('click', () => {
        fields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            field.disabled = false;
            originalValues[fieldId] = field.value; // Store original values
        });
        editButton.style.display = 'none';
        removeButton.style.display = 'none';
        saveButton.style.display = 'inline-block';
        cancelButton.style.display = 'inline-block';
    });
    
    removeButton.addEventListener('click', () => {
        const callingSelect = document.getElementById('calling-form-select');
        const callingId = callingSelect.value;
        const messageContainer = document.getElementById('calling-form-message');
    
        if (!callingId) {
            messageContainer.textContent = "Please select a calling to remove.";
            return;
        }
    
        const confirmation = confirm("Are you sure you want to remove this calling?");
        if (!confirmation) return;
    
        fetch('remove_calling.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `calling_id=${encodeURIComponent(callingId)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                messageContainer.textContent = "Calling removed successfully.";
                document.getElementById('calling-form').reset(); // Clear form fields
                callingSelect.remove(callingSelect.selectedIndex); // Remove the deleted member from dropdown
            } else {
                messageContainer.textContent = "Error: " + data.message;
            }
        })
        .catch(error => {
            console.error("Error removing calling:", error);
            messageContainer.textContent = "An error occurred while removing the calling.";
        });
        
        resetForm()
    });

    // Save button event listener
    saveButton.addEventListener('click', () => {
        const updatedData = {};
        const messageContainer = document.getElementById('calling-form-message');
        fields.forEach(fieldId => {
            updatedData[fieldId] = document.getElementById(fieldId).value;
        });
        console.log(updatedData);
        fetch('update_calling.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(updatedData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                messageContainer.textContent = 'Calling updated successfully.';
            } else {
                messageContainer.textContent = 'Failed to update calling.';
            }
            resetForm();
        })
        .catch(error => {
            console.error('Error:', error);
            messageContainer.textContent = 'An error occurred. Please try again.';
            resetForm();
        });
    });

    // Cancel button event listener
    cancelButton.addEventListener('click', () => {
        fields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            field.value = originalValues[fieldId]; // Revert to original values
            field.disabled = true;
        });
        editButton.style.display = 'inline-block';
        removeButton.style.display = 'inline-block';
        saveButton.style.display = 'none';
        cancelButton.style.display = 'none';
        callingSelect.selectedIndex = 0;
    });
    
    function resetForm() {
        fields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            field.disabled = true;
            field.value = ""; // Restore original values
        });
        editButton.style.display = 'inline-block';
        removeButton.style.display = 'inline-block';
        saveButton.style.display = 'none';
        cancelButton.style.display = 'none';
        callingSelect.selectedIndex = 0;

    }
    
    fetchCallings(); // Populate dropdown
}

// Function to populate the Members dropdown
function fetchMembers() {
    fetch('get_members.php')
        .then(response => response.json())
        .then(data => {
            const memberSelect = document.getElementById('member-form-select');
            data.forEach(member => {
                const option = document.createElement('option');
                option.value = member.member_id;
                option.textContent = `${member.first_name} ${member.last_name}`;
                memberSelect.appendChild(option);
            });

            // Listener to fetch details on selection
            memberSelect.addEventListener('change', function () {
                const memberId = this.value;
                if (memberId) fetchMemberDetails(memberId);
                if (memberId) fetchCallingsForMember(memberId);
                if (memberId) fetchPossibleCallingsForMember(memberId);
            });
        })
        .catch(error => console.error('Error fetching members:', error));
}

// Function to populate the Callings dropdown
function fetchCallings() {
    fetch('get_callings.php')
        .then(response => response.json())
        .then(data => {
            const callingSelect = document.getElementById('calling-form-select');
            data.forEach(calling => {
                const option = document.createElement('option');
                option.value = calling.calling_id;
                option.textContent = calling.calling_name;
                callingSelect.appendChild(option);
            });

            // Listener to fetch details on selection
            callingSelect.addEventListener('change', function () {
                const callingId = this.value;
                if (callingId) fetchCallingDetails(callingId);
                if (callingId) fetchMembersForCalling(callingId);
                if (callingId) fetchPossibleMembersForCalling(callingId);
            });
        })
        .catch(error => console.error('Error fetching callings:', error));
}

// Fetch specific member details
function fetchMemberDetails(memberId) {
    fetch(`get_member_details.php?member_id=${encodeURIComponent(memberId)}`)
        .then(response => response.json())
        .then(member => {
            document.getElementById('member-id').value = member.member_id;
            document.getElementById('first-name').value = member.first_name;
            document.getElementById('last-name').value = member.last_name;
            document.getElementById('gender').value = member.gender;
            document.getElementById('birthdate').value = member.birthdate;
        })
        .catch(error => console.error('Error fetching member details:', error));
    
    /* document.getElementById('edit-member-btn').style.display = 'inline';
    document.getElementById('remove-member-btn').style.display = 'inline';    */
}

// Fetch specific calling details
function fetchCallingDetails(callingId) {
    fetch(`get_calling_details.php?calling_id=${encodeURIComponent(callingId)}`)
        .then(response => response.json())
        .then(calling => {
            document.getElementById('calling-id').value = calling.calling_id;
            document.getElementById('calling-name').value = calling.calling_name;
            document.getElementById('organization').value = calling.organization;
            document.getElementById('grouping').value = calling.grouping;
            document.getElementById('priority').value = calling.priority;
        })
        .catch(error => console.error('Error fetching calling details:', error));
}



// Function to enable adding a new member
function enableAddNewMember() {
    // Clear dropdown selection
    document.getElementById('member-form-select').value = '';

    // Show input fields and hide display spans
    document.getElementById('first-name-display').style.display = 'none';
    document.getElementById('first-name-input').style.display = 'inline-block';

    document.getElementById('last-name-display').style.display = 'none';
    document.getElementById('last-name-input').style.display = 'inline-block';

    document.getElementById('gender-display').style.display = 'none';
    document.getElementById('gender-input').style.display = 'inline-block';

    document.getElementById('birthdate-display').style.display = 'none';
    document.getElementById('birthdate-input').style.display = 'inline-block';

    // Show the Save button
    document.getElementById('save-member-btn').style.display = 'inline-block';
}

// Function to save a new member
function saveNewMember() {
    const firstName = document.getElementById('first-name-input').value;
    const lastName = document.getElementById('last-name-input').value;
    const gender = document.getElementById('gender-input').value;
    const birthdate = document.getElementById('birthdate-input').value;

    if (!firstName || !lastName || !gender || !birthdate) {
        alert("Please fill in all fields.");
        return;
    }

    fetch('add_member.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `first_name=${encodeURIComponent(firstName)}&last_name=${encodeURIComponent(lastName)}&gender=${encodeURIComponent(gender)}&birthdate=${encodeURIComponent(birthdate)}`
    })
    .then(response => response.text())
    .then(data => {
        closeAddMemberModal();
        
        // Optionally reload the dropdown list to include the new member
        fetchMembers();
    })
    .catch(error => console.error('Error:', error));
}


// Function to save a new calling
function saveNewCalling() {
    const callingName = document.getElementById('calling-name-input').value;
    const callingOrganization = document.getElementById('calling-organization-input').value;
    const callingGrouping = document.getElementById('calling-grouping-input').value;
    const callingPriority = document.getElementById('calling-priority-input').value;

    if (!callingName || !callingOrganization || !callingGrouping || !callingPriority) {
        alert("Please fill in all fields.");
        return;
    }

    fetch('add_new_calling.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `calling_name=${encodeURIComponent(callingName)}&organization=${encodeURIComponent(callingOrganization)}&grouping=${encodeURIComponent(callingGrouping)}&priority=${encodeURIComponent(callingPriority)}`
    })
    .then(response => response.text())
    .then(data => {
        closeAddCallingModal();
        
        // Optionally reload the dropdown list to include the new member
        fetchCallings();
    })
    .catch(error => console.error('Error:', error));
}




// Function to open the Add New Member modal
function openAddMemberModal() {
    document.getElementById('add-member-modal').style.display = 'block';
    document.getElementById('add-member-overlay').style.display = 'block';
}

// Function to close the Add New Member modal
function closeAddMemberModal() {
    document.getElementById('add-member-modal').style.display = 'none';
    document.getElementById('add-member-overlay').style.display = 'none';
    

    // Reset modal fields when closed
    document.getElementById('first-name-input').value = '';
    document.getElementById('last-name-input').value = '';
    document.getElementById('gender-input').value = '';
    document.getElementById('birthdate-input').value = '';
}

// Function to open the Add New Member modal
function openAddCallingModal() {
    document.getElementById('add-calling-modal').style.display = 'block';
    document.getElementById('add-calling-overlay').style.display = 'block';
}

// Function to close the Add New Member modal
function closeAddCallingModal() {
    document.getElementById('add-calling-modal').style.display = 'none';
    document.getElementById('add-calling-overlay').style.display = 'none';

    // Reset modal fields when closed
    document.getElementById('calling-name-input').value = '';
    document.getElementById('calling-organization-input').value = '';
    document.getElementById('calling-grouping-input').value = '';
    document.getElementById('calling-priority-input').value = '';
}








//script for handling authentication on app startup
document.addEventListener('DOMContentLoaded', function() {
    const authForm = document.getElementById('auth-form');
    const appContent = document.getElementById('app-content');
    const authMessage = document.getElementById('auth-message');

    authForm.addEventListener('submit', function(event) {
        event.preventDefault();

        const formData = new FormData(authForm);
        fetch('authenticate.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show the application content and hide the auth form
                appContent.style.display = 'block';
                document.getElementById('auth-container').style.display = 'none';
            } else {
                authMessage.textContent = 'Invalid PIN. Please try again.';
            }
        })
        .catch(error => console.error('Error:', error));
    });
});










