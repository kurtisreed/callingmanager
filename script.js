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
        // Only clear dropdowns if we're not coming from a calling process with data
        if (!data || !data.fromProcess) {
            document.getElementById('member-select').value = "";
            document.getElementById('calling-select').value = "";
        }
        document.getElementById('member-callings-container').innerHTML = "";
        document.getElementById('calling-members-container').innerHTML = "";
        
        // Initialize search functionality for Tab 2
        // Don't refresh data if we're coming from calling process (it will wipe our selections)
        initializeTab2Search(data && data.fromProcess);
    }
    
    if (tabName === 'Tab3') {
        loadMembersForm();
    }
    
    if (tabName === 'Tab4') {
        loadCallingsForm();
    }
    
    if (tabName === 'Tab5') {
        loadCallingProcessPage();
    }
    
    // Update mobile menu current tab text
    updateMobileTabText(tabName);    
}

// Function to toggle mobile menu
function toggleMobileMenu() {
    const dropdown = document.getElementById('mobile-dropdown');
    dropdown.classList.toggle('show');
}

// Function to update mobile tab text
function updateMobileTabText(tabName) {
    const currentTabText = document.querySelector('.current-tab-text');
    if (currentTabText) {
        const tabTexts = {
            'Tab1': 'Callings Overview',
            'Tab2': 'Assign/Release Callings', 
            'Tab3': 'Member Information',
            'Tab4': 'Calling Information',
            'Tab5': 'Calling Process'
        };
        currentTabText.textContent = tabTexts[tabName] || 'Menu';
    }
}

// Close mobile menu when clicking outside
document.addEventListener('click', function(event) {
    const mobileNav = document.querySelector('.mobile-nav');
    const dropdown = document.getElementById('mobile-dropdown');
    
    if (mobileNav && dropdown && !mobileNav.contains(event.target)) {
        dropdown.classList.remove('show');
    }
});

// Override mobile tab links to close dropdown
document.addEventListener('DOMContentLoaded', function() {
    const mobileTabLinks = document.querySelectorAll('.mobile-tablink');
    mobileTabLinks.forEach(link => {
        link.addEventListener('click', function() {
            document.getElementById('mobile-dropdown').classList.remove('show');
        });
    });
});

// Function to initialize Tab 2 search functionality
function initializeTab2Search(skipRefresh = false) {
    // Set up member search
    const memberSearchInput = document.getElementById('member-search-tab2');
    if (memberSearchInput && !memberSearchInput.hasAttribute('data-listener-added')) {
        memberSearchInput.addEventListener('input', function() {
            filterTab2Members();
        });
        memberSearchInput.setAttribute('data-listener-added', 'true');
    }
    
    // Set up calling search
    const callingSearchInput = document.getElementById('calling-search-tab2');
    if (callingSearchInput && !callingSearchInput.hasAttribute('data-listener-added')) {
        callingSearchInput.addEventListener('input', function() {
            filterTab2Callings();
        });
        callingSearchInput.setAttribute('data-listener-added', 'true');
    }
    
    // Clear search inputs
    if (memberSearchInput) memberSearchInput.value = '';
    if (callingSearchInput) callingSearchInput.value = '';
    
    // Only refresh data if not skipping (to preserve dropdown selections from calling process)
    if (!skipRefresh) {
        refreshTab2Data();
    }
}

// Function to refresh Tab 2 data
function refreshTab2Data() {
    // Refresh member data
    fetch('get_members.php')
        .then(response => response.json())
        .then(data => {
            allTab2MembersData = data;
            displayTab2Members(data);
        })
        .catch(error => console.error('Error refreshing members:', error));
    
    // Refresh calling data  
    fetch('get_callings.php')
        .then(response => response.json())
        .then(data => {
            allTab2CallingsData = data;
            displayTab2Callings(data);
        })
        .catch(error => console.error('Error refreshing callings:', error));
}

// Function to display members in Tab 2 dropdown
function displayTab2Members(membersData) {
    const memberSelect = document.getElementById('member-select');
    if (!memberSelect) return;
    
    memberSelect.innerHTML = '<option value="">Select a Member</option>';
    
    membersData.forEach(member => {
        const option = document.createElement('option');
        option.value = member.member_id;
        
        // Add status indicator
        const statusBadge = getStatusBadge(member.status);
        option.textContent = `${member.first_name} ${member.last_name} ${statusBadge}`;
        
        memberSelect.appendChild(option);
    });
}

// Function to filter Tab 2 members based on search
function filterTab2Members() {
    const searchTerm = document.getElementById('member-search-tab2').value.toLowerCase();
    
    // If no data is available yet, don't filter
    if (!allTab2MembersData || allTab2MembersData.length === 0) {
        console.log('No member data available for filtering');
        return;
    }
    
    let filteredData = allTab2MembersData;
    
    if (searchTerm) {
        filteredData = filteredData.filter(member => 
            (member.first_name || '').toLowerCase().includes(searchTerm) ||
            (member.last_name || '').toLowerCase().includes(searchTerm) ||
            `${member.first_name || ''} ${member.last_name || ''}`.toLowerCase().includes(searchTerm)
        );
    }
    
    displayTab2Members(filteredData);
}

// Function to display callings in Tab 2 dropdown
function displayTab2Callings(callingsData) {
    const callingSelect = document.getElementById('calling-select');
    if (!callingSelect) return;
    
    callingSelect.innerHTML = '<option value="">Select a Calling</option>';
    
    callingsData.forEach(calling => {
        const option = document.createElement('option');
        option.value = calling.calling_id;
        option.textContent = calling.calling_name;
        callingSelect.appendChild(option);
    });
}

// Function to filter Tab 2 callings based on search
function filterTab2Callings() {
    const searchTerm = document.getElementById('calling-search-tab2').value.toLowerCase();
    
    // If no data is available yet, don't filter
    if (!allTab2CallingsData || allTab2CallingsData.length === 0) {
        console.log('No calling data available for filtering');
        return;
    }
    
    let filteredData = allTab2CallingsData;
    
    if (searchTerm) {
        filteredData = filteredData.filter(calling => 
            (calling.calling_name || '').toLowerCase().includes(searchTerm) ||
            (calling.organization || '').toLowerCase().includes(searchTerm) ||
            (calling.grouping || '').toLowerCase().includes(searchTerm)
        );
    }
    
    displayTab2Callings(filteredData);
}

// Global flag to track authentication status
let isUserAuthenticated = false;

// Helper function for authenticated API calls
function authenticatedFetch(url, options = {}) {
    if (!isUserAuthenticated) {
        console.log('API call blocked: User not authenticated');
        return Promise.reject(new Error('User not authenticated'));
    }
    
    return fetch(url, options)
        .then(response => {
            // Check if the response indicates an authentication error
            if (response.status === 401) {
                console.log('Authentication expired, redirecting to login');
                isUserAuthenticated = false;
                location.reload(); // Reload to show login form
                throw new Error('Authentication expired');
            }
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            return response;
        })
        .catch(error => {
            console.error('API call failed:', error);
            throw error;
        });
}

// Load default tab only after authentication
function initializeApplication() {
    isUserAuthenticated = true;
    
    // Now it's safe to load the default tab and data
    document.querySelector('.tablinks').click(); // Simulate click on first tab to load content
    
    // Initialize dropdowns for Tab2
    populateMembers();
    populateCallings();
}

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


function populateMembers(selectedMemberId = '', callback = null) {
    // Check authentication before making request
    if (!isUserAuthenticated) {
        console.log('Cannot populate members: User not authenticated');
        return;
    }
    
    console.log('PopulateMembers called with selectedMemberId:', selectedMemberId);
    
    fetch('get_members.php')
        .then(response => response.json())
        .then(data => {
            console.log('Members data received:', data.length, 'members');
            const memberSelect = document.getElementById('member-select');
            memberSelect.innerHTML = '<option value="">Select a Member</option>';
            data.forEach(member => {
                const option = document.createElement('option');
                option.value = member.member_id;
                option.textContent = `${member.first_name} ${member.last_name}`;
                memberSelect.appendChild(option);
            });
            
            console.log('Member dropdown populated, setting value to:', selectedMemberId);
            
            // Set the value and trigger selection logic if needed
            if (selectedMemberId) {
                memberSelect.value = selectedMemberId;
                console.log('Member dropdown value set. Current value:', memberSelect.value);
                console.log('Selected index:', memberSelect.selectedIndex);
                
                if (memberSelect.selectedIndex > 0) {
                    const memberName = memberSelect.options[memberSelect.selectedIndex].text;
                    console.log('Member name from selected option:', memberName);
                    
                    // Force the member selection logic to run with delay
                    setTimeout(() => {
                        fetchCurrentCallings(selectedMemberId, memberName);
                    }, 100);
                    
                    // Also try dispatching the event
                    memberSelect.dispatchEvent(new Event('change', { bubbles: true }));
                } else {
                    console.error('Member not found in dropdown options');
                }
                
                if (callback) callback();
            } else if (callback) {
                callback();
            }
        })
        .catch(error => {
            console.error('Error fetching members:', error);
            if (callback) callback();
        });
}

function populateCallings(selectedCallingId = '', callback = null) {
    // Check authentication before making request
    if (!isUserAuthenticated) {
        console.log('Cannot populate callings: User not authenticated');
        return;
    }
    
    console.log('PopulateCallings called with selectedCallingId:', selectedCallingId);
    
    fetch('get_callings.php')
        .then(response => response.json())
        .then(data => {
            console.log('Callings data received:', data.length, 'callings');
            const callingSelect = document.getElementById('calling-select');
            callingSelect.innerHTML = '<option value="">Select a Calling</option>';
            data.forEach(calling => {
                const option = document.createElement('option');
                option.value = calling.calling_id;
                option.textContent = calling.calling_name;
                callingSelect.appendChild(option);
            });
            
            console.log('Calling dropdown populated, setting value to:', selectedCallingId);
            
            // Set the value and trigger selection logic if needed
            if (selectedCallingId) {
                callingSelect.value = selectedCallingId;
                console.log('Calling dropdown value set. Current value:', callingSelect.value);
                console.log('Selected index:', callingSelect.selectedIndex);
                
                if (callingSelect.selectedIndex > 0) {
                    const callingName = callingSelect.options[callingSelect.selectedIndex].text;
                    console.log('Calling name from selected option:', callingName);
                    
                    // Force the calling selection logic to run with delay
                    setTimeout(() => {
                        fetchCallingMembers(selectedCallingId, callingName);
                    }, 200); // Start after member logic has had time to run
                    
                    // Also try dispatching the event
                    callingSelect.dispatchEvent(new Event('change', { bubbles: true }));
                } else {
                    console.error('Calling not found in dropdown options');
                }
                
                if (callback) callback();
            } else if (callback) {
                callback();
            }
        })
        .catch(error => {
            console.error('Error fetching callings:', error);
            if (callback) callback();
        });
}

// Update handleDataForTab to use the new functions:
function handleDataForTab(tabName, data) {
    if (tabName === 'Tab2') {
        console.log('HandleDataForTab called with data:', data);
        console.log('Member ID:', data.memberId, 'Calling ID:', data.callingId);
        
        // Store process information if coming from calling process
        if (data.fromProcess && data.processId) {
            window.callingProcessData = {
                processId: data.processId,
                fromProcess: true
            };
        } else {
            // Clear any existing process data
            window.callingProcessData = null;
        }
        
        // Simple, direct approach - just populate dropdowns and simulate user selections
        if (data.memberId && data.callingId && data.memberName && data.callingName) {
            simulateUserSelections(data.memberId, data.callingId, data.memberName, data.callingName);
        } else {
            // Fallback to old approach if we don't have all the data
            populateMembers(data.memberId || '');
            populateCallings(data.callingId || '');
        }
    }
}

// New function: Simulate exactly what happens when a user makes selections
function simulateUserSelections(memberId, callingId, memberName, callingName) {
    console.log('Simulating user selections:', {memberId, callingId, memberName, callingName});
    
    // Step 1: Populate both dropdowns without any events or callbacks
    Promise.all([
        populateDropdownOnly('member-select', 'get_members.php', memberId, 'member_id', (m) => `${m.first_name} ${m.last_name}`),
        populateDropdownOnly('calling-select', 'get_callings.php', callingId, 'calling_id', (c) => c.calling_name)
    ]).then(() => {
        console.log('Both dropdowns populated, now simulating user actions');
        
        // Step 2: Do exactly what the user selection would do
        // This is what happens when user selects a member:
        fetchCurrentCallings(memberId, memberName);
        
        // This is what happens when user selects a calling:
        fetchCallingMembers(callingId, callingName);
        fetchOtherCandidates(callingId);
        
        // This is what happens after both selections:
        setTimeout(() => {
            updateChangesPreview();
            console.log('User simulation complete');
        }, 300);
    });
}

// Helper function to populate dropdown without events
function populateDropdownOnly(selectId, endpoint, selectedValue, valueField, textFunction) {
    return fetch(endpoint)
        .then(response => response.json())
        .then(data => {
            const select = document.getElementById(selectId);
            select.innerHTML = `<option value="">Select a ${selectId.includes('member') ? 'Member' : 'Calling'}</option>`;
            
            data.forEach(item => {
                const option = document.createElement('option');
                option.value = item[valueField];
                option.textContent = textFunction(item);
                select.appendChild(option);
            });
            
            if (selectedValue) {
                select.value = selectedValue;
                console.log(`${selectId} set to:`, selectedValue, 'Selected index:', select.selectedIndex);
            }
        })
        .catch(error => console.error(`Error populating ${selectId}:`, error));
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
    // Check authentication before making request
    if (!isUserAuthenticated) {
        console.log('Cannot show popup: User not authenticated');
        return;
    }
    
    // Set the modal title
    document.getElementById('popup-title').textContent = title + " Candidates";
    const popupTitle = document.getElementById("popup-title"); // or use querySelector if you need
    popupTitle.setAttribute("data-calling-id", callingId);
    
    // Reset search and filter inputs to default
    document.getElementById('candidate-search').value = "";  // Clear search
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
    // Check authentication before making request
    if (!isUserAuthenticated) {
        console.log('Cannot load calling comments: User not authenticated');
        return;
    }
    
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
    // Check authentication before making request
    if (!isUserAuthenticated) {
        console.log('Cannot save calling comments: User not authenticated');
        return;
    }
    
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
    const searchTerm = document.getElementById('candidate-search').value.toLowerCase();
    const gender = document.getElementById('gender-filter').value;
    const ageGroup = document.getElementById('age-filter').value;

    const filteredCandidates = allCandidates.filter(candidate => {
        let matchesSearch = true;
        let matchesGender = true;
        let matchesAge = true;

        // Check name search filter
        if (searchTerm) {
            // Handle both field name formats (popup uses "First Name", member info uses "first_name")
            const firstName = candidate['First Name'] || candidate.first_name || '';
            const lastName = candidate['Last Name'] || candidate.last_name || '';
            matchesSearch = firstName.toLowerCase().includes(searchTerm) ||
                          lastName.toLowerCase().includes(searchTerm) ||
                          `${firstName} ${lastName}`.toLowerCase().includes(searchTerm);
        }

        // Check gender filter
        if (gender) {
            matchesGender = candidate.gender === gender;
        }

        // Check age filter
        if (ageGroup) {
            const age = calculateAge(candidate.birthdate); // Calculate age from birthdate
            matchesAge = ageGroup === 'under_18' ? age < 18 : age >= 18;
        }

        return matchesSearch && matchesGender && matchesAge;
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
    buildSmallBoxes(); // Rebuild the small boxes to reflect any changes
}

// Function to add member to possible callings
function addToPossibleCallings(memberId, callingId) {
    // Check authentication before making request
    if (!isUserAuthenticated) {
        console.log('Cannot add to possible callings: User not authenticated');
        return;
    }
    
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
    // Add the calling to the process instead of direct assignment
    addToCallingProcess(memberId, callingId);
}

// Function to add calling to the process system
function addToCallingProcess(memberId, callingId) {
    const data = {
        member_id: memberId,
        calling_id: callingId,
        proposed_by: 'User Selection',
        notes: 'Added from candidate selection'
    };

    fetch('add_calling_process.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            alert(`✓ ${result.message}\n\nThe calling has been added to the process and can be managed in the Calling Process tab.`);
            closePopup();
            // Optionally redirect to the calling process tab
            openTab(null, 'Tab5');
        } else {
            alert(`Error: ${result.message}`);
        }
    })
    .catch(error => {
        console.error('Error adding to calling process:', error);
        alert('An error occurred while adding the calling to the process. Please try again.');
    });
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
    
    // Check if user is authenticated before making requests
    if (!isUserAuthenticated) {
        largeBox.innerHTML = `
            <div style="text-align: center; padding: 50px; color: #666;">
                <h3>Welcome to Calling Manager</h3>
                <p>Please log in to view and manage callings data.</p>
            </div>
        `;
        return;
    }
    
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

// attaches popup listeners to each box title
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
                allTab2MembersData = data; // Store for search filtering
                displayTab2Members(data);
            })
            .catch(error => console.error('Error fetching members:', error));
    }

    // Function to populate callings dropdown
    function populateCallings() {
        fetch('get_callings.php')
            .then(response => response.json())
            .then(data => {
                allTab2CallingsData = data; // Store for search filtering
                displayTab2Callings(data);
            })
            .catch(error => console.error('Error fetching callings:', error));
    }

    // Note: populateMembers() and populateCallings() are now called 
    // in initializeApplication() after authentication
    
    // Set the default date for the Date Set Apart field
    const dateSetApartField = document.getElementById('date-set-apart');
    const today = new Date().toISOString().substr(0, 10); // Get today's date in YYYY-MM-DD format
    dateSetApartField.value = today;

    // Listen for member selection changes to check for current callings
    document.getElementById('member-select').addEventListener('change', function() {
        const memberId = this.value;

        if (memberId) {
            const memberName = this.options[this.selectedIndex].text;
            fetchCurrentCallings(memberId, memberName);
        } else {
            document.getElementById('member-callings-container').innerHTML = ''; // Clear the table if no member is selected
        }
        
        // Refresh other candidates list if a calling is selected
        const callingId = document.getElementById('calling-select').value;
        if (callingId) {
            fetchOtherCandidates(callingId);
        }
        
        updateChangesPreview();
    });
    
    


    // Handle "Make Proposed Changes" button click
    const makeChangesBtn = document.getElementById('make-changes-btn');
    if (makeChangesBtn) {
        makeChangesBtn.addEventListener('click', function() {
            makeProposedChanges();
        });
    }

});

// Function to make all proposed changes
function makeProposedChanges() {
    const memberSelect = document.getElementById('member-select');
    const callingSelect = document.getElementById('calling-select');
    const dateField = document.getElementById('date-set-apart');
    
    console.log('Date field element:', dateField);
    console.log('Date field value:', dateField ? dateField.value : 'not found');
    
    if (!dateField) {
        document.getElementById('form-response').textContent = 'Date field not found.';
        return;
    }
    
    const selectedMember = memberSelect.value;
    const selectedCalling = callingSelect.value;
    const changeDate = dateField.value;
    
    console.log('Change date:', changeDate);
    
    if (!changeDate) {
        document.getElementById('form-response').textContent = 'Please select a date for the changes.';
        return;
    }
    
    // Collect all releases
    const memberReleases = [];
    document.querySelectorAll('.release-calling-checkbox:checked').forEach(checkbox => {
        memberReleases.push(checkbox.value);
    });
    
    const callingReleases = [];
    document.querySelectorAll('.release-member-checkbox:checked').forEach(checkbox => {
        callingReleases.push(checkbox.value);
    });
    
    // Check if there are actually changes to make
    if (!selectedMember && !selectedCalling && memberReleases.length === 0 && callingReleases.length === 0) {
        document.getElementById('form-response').textContent = 'No changes selected.';
        return;
    }
    
    // Check if we need to handle possible_callings updates
    const removeOtherCandidatesCheckbox = document.getElementById('remove-other-candidates-checkbox');
    const shouldRemoveOtherCandidates = removeOtherCandidatesCheckbox && removeOtherCandidatesCheckbox.checked;

    // Prepare data for the new endpoint
    const changeData = {
        member_id: selectedMember || null,
        calling_id: selectedCalling || null,
        change_date: changeDate,
        member_releases: memberReleases,
        calling_releases: callingReleases,
        update_possible_callings: selectedMember && selectedCalling ? true : false,
        remove_other_candidates: shouldRemoveOtherCandidates
    };
    
    console.log('Sending change data:', changeData);
    
    // Send to new endpoint
    fetch('make_calling_changes.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(changeData)
    })
    .then(response => response.text())
    .then(data => {
        document.getElementById('form-response').textContent = data;
        
        // If this was from a calling process and the assignment was successful, remove the process
        if (window.callingProcessData && window.callingProcessData.fromProcess && data.includes('successfully')) {
            removeCompletedCallingProcess(window.callingProcessData.processId);
        }
        
        // Reset form and clear containers
        if (memberSelect) memberSelect.value = '';
        if (callingSelect) callingSelect.value = '';
        if (dateField) dateField.value = new Date().toISOString().substr(0, 10);
        document.getElementById('member-callings-container').innerHTML = '';
        document.getElementById('calling-members-container').innerHTML = '';
        updateChangesPreview();
        
        // Clear process data
        window.callingProcessData = null;
    })
    .catch(error => {
        console.error('Error making changes:', error);
        document.getElementById('form-response').textContent = 'An error occurred while making changes.';
    });
}

// Function to remove a completed calling process after successful assignment
function removeCompletedCallingProcess(processId) {
    fetch('cancel_calling_process.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ id: processId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Calling process removed successfully after assignment completion');
        } else {
            console.error('Failed to remove calling process:', data.message);
        }
    })
    .catch(error => {
        console.error('Error removing calling process:', error);
    });
}

// Function to update the changes preview based on current selections
function updateChangesPreview() {
    const memberSelect = document.getElementById('member-select');
    const callingSelect = document.getElementById('calling-select');
    const previewDiv = document.getElementById('changes-preview');
    const changesText = document.getElementById('changes-text');
    
    if (!memberSelect || !callingSelect || !previewDiv || !changesText) {
        return; // Elements not found
    }
    
    const selectedMember = memberSelect.value;
    const selectedCalling = callingSelect.value;
    const selectedMemberName = selectedMember ? memberSelect.options[memberSelect.selectedIndex].text : '';
    const selectedCallingName = selectedCalling ? callingSelect.options[callingSelect.selectedIndex].text : '';
    
    // If neither member nor calling is selected, hide preview
    if (!selectedMember && !selectedCalling) {
        previewDiv.style.display = 'none';
        return;
    }
    
    let changes = [];
    
    // Check for member releases (from member-callings table)
    const memberReleaseCheckboxes = document.querySelectorAll('.release-calling-checkbox:checked');
    let memberReleases = [];
    memberReleaseCheckboxes.forEach(checkbox => {
        const row = checkbox.closest('tr');
        const callingCell = row.cells[2]; // Calling Name column
        if (callingCell) {
            memberReleases.push(callingCell.textContent);
        }
    });
    
    // Check for calling releases (from calling-members table)
    const callingReleaseCheckboxes = document.querySelectorAll('.release-member-checkbox:checked');
    let callingReleases = [];
    callingReleaseCheckboxes.forEach(checkbox => {
        const row = checkbox.closest('tr');
        const memberCell = row.cells[2]; // Member Name column
        if (memberCell) {
            callingReleases.push(memberCell.textContent);
        }
    });
    
    // Build the changes description
    if (selectedMember && selectedCalling) {
        // Main assignment
        changes.push(`${selectedMemberName} will be assigned to ${selectedCallingName}`);
        
        // Member releases
        if (memberReleases.length > 0) {
            const releasesText = memberReleases.length === 1 ? 
                memberReleases[0] : 
                memberReleases.slice(0, -1).join(', ') + ' and ' + memberReleases.slice(-1);
            changes.push(`${selectedMemberName} will be released from ${releasesText}`);
        }
        
        // Calling releases (other people being released from this calling)
        if (callingReleases.length > 0) {
            callingReleases.forEach(memberName => {
                changes.push(`${memberName} will be released from ${selectedCallingName}`);
            });
        }
    } else if (selectedMember && memberReleases.length > 0) {
        // Only releases, no assignment
        const releasesText = memberReleases.length === 1 ? 
            memberReleases[0] : 
            memberReleases.slice(0, -1).join(', ') + ' and ' + memberReleases.slice(-1);
        changes.push(`${selectedMemberName} will be released from ${releasesText}`);
    } else if (selectedCalling && callingReleases.length > 0) {
        // Only calling releases, no assignment
        callingReleases.forEach(memberName => {
            changes.push(`${memberName} will be released from ${selectedCallingName}`);
        });
    }
    
    const makeChangesBtn = document.getElementById('make-changes-btn');
    
    if (changes.length > 0) {
        // Join changes with proper punctuation
        let changeText = changes.join(', ');
        // Add proper punctuation at the end
        if (!changeText.endsWith('.')) {
            changeText += '.';
        }
        // Capitalize first letter
        changeText = changeText.charAt(0).toUpperCase() + changeText.slice(1);
        
        changesText.textContent = changeText;
        previewDiv.style.display = 'block';
        
        // Enable the button
        if (makeChangesBtn) {
            makeChangesBtn.disabled = false;
        }
    } else {
        previewDiv.style.display = 'none';
        
        // Disable the button
        if (makeChangesBtn) {
            makeChangesBtn.disabled = true;
        }
    }
}

// Fetch and display current callings of the selected member
function fetchCurrentCallings(memberId, memberName) {
    // Check authentication before making request
    if (!isUserAuthenticated) {
        console.log('Cannot fetch current callings: User not authenticated');
        return;
    }
    
    fetch(`get_member_callings.php?member_id=${encodeURIComponent(memberId)}`)
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('member-callings-container');
            container.innerHTML = ''; // Clear previous content

            // Add title with member name
            let content = `<h4>${memberName}: Current Callings</h4>`;

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
                content += table;
            } else {
                content += '<p>No current or past callings for this member.</p>';
            }

            container.innerHTML = content;
            
            // Add event listeners for checkbox changes and update preview
            const checkboxes = container.querySelectorAll('.release-calling-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateChangesPreview);
            });
            updateChangesPreview();
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
        const callingName = this.options[this.selectedIndex].text;
        fetchCallingMembers(callingId, callingName);
        fetchOtherCandidates(callingId);
    } else {
        document.getElementById('calling-members-container').innerHTML = ''; // Clear the table if no calling is selected
        hideOtherCandidatesSection();
    }
    updateChangesPreview();
});

// Function to fetch other members being considered for the same calling
function fetchOtherCandidates(callingId) {
    const selectedMemberId = document.getElementById('member-select').value;
    
    fetch(`get_possible_callings.php?newCalling=${callingId}`)
        .then(response => response.json())
        .then(data => {
            // Filter out the currently selected member
            const otherCandidates = data.filter(candidate => 
                candidate['Member ID'] != selectedMemberId
            );
            
            displayOtherCandidates(otherCandidates);
        })
        .catch(error => {
            console.error('Error fetching other candidates:', error);
            hideOtherCandidatesSection();
        });
}

// Function to display other candidates in the checkbox section
function displayOtherCandidates(candidates) {
    const section = document.getElementById('remove-other-candidates-section');
    const list = document.getElementById('other-candidates-list');
    
    if (candidates.length === 0) {
        hideOtherCandidatesSection();
        return;
    }
    
    const candidateNames = candidates.map(candidate => 
        `${candidate['First Name']} ${candidate['Last Name']}`
    ).join(', ');
    
    list.textContent = candidateNames;
    section.style.display = 'block';
}

// Function to hide the other candidates section
function hideOtherCandidatesSection() {
    const section = document.getElementById('remove-other-candidates-section');
    const checkbox = document.getElementById('remove-other-candidates-checkbox');
    
    section.style.display = 'none';
    checkbox.checked = false;
}

// Fetch and display members associated with the selected calling
function fetchCallingMembers(callingId, callingName) {
    // Check authentication before making request
    if (!isUserAuthenticated) {
        console.log('Cannot fetch calling members: User not authenticated');
        return;
    }
    
    fetch(`get_calling_members.php?calling_id=${encodeURIComponent(callingId)}`)
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('calling-members-container');
            container.innerHTML = ''; // Clear previous content

            // Add title with calling name
            let content = `<h4>${callingName}: Current Status</h4>`;

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
                content += table;
            } else {
                content += '<p>No members found for this calling.</p>';
            }

            container.innerHTML = content;
            
            // Add event listeners for checkbox changes and update preview
            const checkboxes = container.querySelectorAll('.release-member-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateChangesPreview);
            });
            updateChangesPreview();
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



// Remove old sub-tab functionality - no longer needed

// Function to load the Members form
function loadMembersForm() {
    const container = document.getElementById('member-information-container');
    container.innerHTML = `
        
        <form id="member-form">
            <div class="two-column-layout">
                <!-- Left Column: Controls -->
                <div class="left-column">
                    <!-- Member Selection Section -->
                    <div class="section-header">
                        <h3>👤 Select Member</h3>
                    </div>
                    <div class="selection-section">
                        <label for="member-form-select">Choose Member:</label>
                        <select id="member-form-select">
                            <option value="">Select a Member</option>
                        </select>
                    </div>
                    
                    <!-- Search & Filter Section -->
                    <div class="section-header">
                        <h3>🔍 Search & Filter</h3>
                    </div>
                    <div class="search-section">
                        <div class="search-column">
                            <div class="search-field">
                                <label for="member-search-input">Search Members:</label>
                                <input type="text" id="member-search-input" placeholder="Type name to search..." />
                            </div>
                            <div class="filter-field">
                                <label for="status-filter-select">Filter by Status:</label>
                                <select id="status-filter-select">
                                    <option value="">All Members</option>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="moved">Moved Away</option>
                                    <option value="no_calling">No Current Calling</option>
                                    <option value="deceased">Deceased</option>
                                    <option value="unknown">Status Unknown</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons Section -->
                    <div class="section-header">
                        <h3>⚙️ Actions</h3>
                    </div>
                    <div class="action-buttons">
                        <button id="add-member-btn" type="button" class="action-btn save-btn" onclick="openAddMemberModal()">+ Add New Member</button>
                        <button type="button" id="edit-btn" class="action-btn edit-btn" style="display: none;">Edit Member</button>
                        <button type="button" id="remove-member-btn" class="action-btn remove-btn" style="display: none;">Remove Member</button>
                        <button type="button" id="save-btn" class="action-btn save-btn" style="display: none;">Save Changes</button>
                        <button type="button" id="cancel-btn" class="action-btn cancel-btn" style="display: none;">Cancel</button>
                    </div>
                    <div id="member-form-message" style="margin-top: 10px;"></div>
                    <div id="form-response"></div>
                </div>
                
                <!-- Right Column: Details -->
                <div class="right-column">
                    <!-- Member Information Section -->
                    <div class="section-header">
                        <h3>📋 Member Details</h3>
                    </div>
                    <div id="member-details" class="details-section">

                <label>First Name:</label>
                <input type="text" id="first-name" disabled>
        
                <label>Last Name:</label>
                <input type="text" id="last-name" disabled>
        
                <label>Gender:</label>
                <input type="text" id="gender" disabled>
        
                <label>Birthdate:</label>
                <input type="date" id="birthdate" disabled>

                <label>Status:</label>
                <select id="member-status" disabled>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="moved">Moved Away</option>
                    <option value="no_calling">No Current Calling</option>
                    <option value="deceased">Deceased</option>
                    <option value="unknown">Status Unknown</option>
                </select>

                <label>Status Notes:</label>
                <textarea id="status-notes" disabled rows="3" placeholder="Additional notes about this member's status..."></textarea>
                
                <div id="member-status-display" style="margin-top: 10px;">
                    <!-- Status badge will be displayed here -->
                </div>
            </div>
            
                    <div id="member-callings"></div> 
                    <div id="member-callings-considered"></div>
                </div>
            </div>
        </form>
    `;

    
    // Now add the event listeners for the Edit, Save, and Cancel buttons
    const editButton = document.getElementById('edit-btn');
    const saveButton = document.getElementById('save-btn');
    const cancelButton = document.getElementById('cancel-btn');
    const removeButton = document.getElementById('remove-member-btn');
    const formResponse = document.getElementById('form-response');
    const memberSelect = document.getElementById('member-form-select');
    const fields = ['first-name', 'last-name', 'gender', 'birthdate', 'member-status', 'status-notes'];
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
        
        // Get the member ID from the selected member in dropdown
        const selectedMemberId = memberSelect.value;
        if (!selectedMemberId) {
            messageContainer.textContent = 'Please select a member first.';
            return;
        }
        updatedData['member-id'] = selectedMemberId;
        
        // Get other field values
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
    
    // Add event listeners for search and filtering
    const searchInput = document.getElementById('member-search-input');
    const statusFilter = document.getElementById('status-filter-select');
    
    // Real-time search as user types
    searchInput.addEventListener('input', function() {
        filterMembers();
    });
    
    // Status filter change
    statusFilter.addEventListener('change', function() {
        const selectedStatus = this.value;
        if (selectedStatus) {
            // If filtering by status, fetch fresh data for that status
            fetchMembers(selectedStatus);
        } else {
            // If showing all, fetch all members then apply search filter
            fetchMembers();
        }
        // Clear search when changing status filter
        searchInput.value = '';
    });
    
    fetchMembers(); // Populate dropdown initially
}



// Function to load the Callings form
function loadCallingsForm() {
    const container = document.getElementById('calling-information-container');
    container.innerHTML = `
        
        <form id="calling-form">
            <div class="two-column-layout">
                <!-- Left Column: Controls -->
                <div class="left-column">
                    <!-- Calling Selection Section -->
                    <div class="section-header">
                        <h3>⛪ Select Calling</h3>
                    </div>
                    <div class="selection-section">
                        <label for="calling-form-select">Choose Calling:</label>
                        <select id="calling-form-select">
                            <option value="">Select a Calling</option>
                        </select>
                    </div>
                    
                    <!-- Search & Filter Section -->
                    <div class="section-header">
                        <h3>🔍 Search & Filter</h3>
                    </div>
                    <div class="search-section">
                        <div class="search-column">
                            <div class="search-field">
                                <label for="calling-search-input">Search Callings:</label>
                                <input type="text" id="calling-search-input" placeholder="Type calling name to search..." />
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons Section -->
                    <div class="section-header">
                        <h3>⚙️ Actions</h3>
                    </div>
                    <div class="action-buttons">
                        <button id="add-calling-btn" type="button" class="action-btn save-btn" onclick="openAddCallingModal()">+ Add New Calling</button>
                        <button type="button" id="edit-calling-btn" class="action-btn edit-btn" style="display: none;">Edit Calling</button>
                        <button type="button" id="remove-calling-btn" class="action-btn remove-btn" style="display: none;">Remove Calling</button>
                        <button type="button" id="save-calling-btn" class="action-btn save-btn" style="display: none;">Save Changes</button>
                        <button type="button" id="cancel-calling-btn" class="action-btn cancel-btn" style="display: none;">Cancel</button>
                    </div>
                    <div id="calling-form-message" style="margin-top: 10px;"></div>
                </div>
                
                <!-- Right Column: Details -->
                <div class="right-column">
                    <!-- Calling Information Section -->
                    <div class="section-header">
                        <h3>📋 Calling Details</h3>
                    </div>
                    <div id="calling-details" class="details-section">
                                        
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
                    
                    <div id="callings-for-member"></div>
                    <div id="calling-members-considered"></div>
                </div>
            </div>
        </form>
    `;
    
        // Now add the event listeners for the Edit, Save, and Cancel buttons
    const editButton = document.getElementById('edit-calling-btn');
    const saveButton = document.getElementById('save-calling-btn');
    const cancelButton = document.getElementById('cancel-calling-btn');
    const removeButton = document.getElementById('remove-calling-btn');
    const formResponse = document.getElementById('form-response');
    const callingSelect = document.getElementById('calling-form-select');
    const fields = ['calling-name', 'organization', 'grouping', 'priority'];
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
        
        // Get the calling ID from the selected calling in dropdown
        const selectedCallingId = callingSelect.value;
        if (!selectedCallingId) {
            messageContainer.textContent = 'Please select a calling first.';
            return;
        }
        updatedData['calling-id'] = selectedCallingId;
        
        // Get other field values
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
    
    // Add event listener for search functionality
    const callingSearchInput = document.getElementById('calling-search-input');
    callingSearchInput.addEventListener('input', filterCallings);
    
    fetchCallings(); // Populate dropdown
}

// Function to load the Calling Process page
function loadCallingProcessPage() {
    const container = document.getElementById('calling-process-container');
    container.innerHTML = `
        <div class="two-column-layout">
            <!-- Left Column: Controls -->
            <div class="left-column">
                <!-- Filter Section -->
                <div class="section-header">
                    <h3>🔍 Filter</h3>
                </div>
                <div class="search-section">
                    <div class="search-column">
                        <div class="filter-field">
                            <label for="process-status-filter">Status:</label>
                            <select id="process-status-filter">
                                <option value="">All Statuses</option>
                                <option value="approved">Approved</option>
                                <option value="interviewed">Interviewed</option>
                                <option value="sustained">Sustained</option>
                                <option value="set_apart">Set Apart</option>
                            </select>
                        </div>
                        <div class="search-field">
                            <label for="process-search-input">Search:</label>
                            <input type="text" id="process-search-input" placeholder="Member or calling name..." />
                        </div>
                    </div>
                </div>

                <!-- Stats Section -->
                <div class="section-header">
                    <h3>📊 Process Stats</h3>
                </div>
                <div class="details-section">
                    <div id="process-stats">
                        <div class="stat-item">
                            <span class="stat-label">Approved:</span>
                            <span class="stat-value" id="stat-approved">0</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Interviewed:</span>
                            <span class="stat-value" id="stat-interviewed">0</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Sustained:</span>
                            <span class="stat-value" id="stat-sustained">0</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Set Apart:</span>
                            <span class="stat-value" id="stat-set-apart">0</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Column: Process Table -->
            <div class="right-column">
                <div class="section-header">
                    <h3>📋 Callings in Process</h3>
                </div>
                <div class="details-section">
                    <div id="calling-process-table-container">
                        <table id="calling-process-table">
                            <thead>
                                <tr>
                                    <th>Member</th>
                                    <th>Proposed Calling</th>
                                    <th>Approved Date</th>
                                    <th>Progress</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="process-table-body">
                                <tr>
                                    <td colspan="5" class="no-data">Loading calling processes...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Add event listeners
    const statusFilter = document.getElementById('process-status-filter');
    const searchInput = document.getElementById('process-search-input');
    
    if (statusFilter) {
        statusFilter.addEventListener('change', filterCallingProcesses);
    }
    
    if (searchInput) {
        searchInput.addEventListener('input', filterCallingProcesses);
    }
    
    // Load the calling processes
    fetchCallingProcesses();
}

// Function to fetch calling processes from server
function fetchCallingProcesses() {
    fetch('get_calling_processes.php')
        .then(response => response.json())
        .then(data => {
            allCallingProcessesData = data; // Store data for filtering
            displayCallingProcesses(data);
            updateProcessStats(data);
        })
        .catch(error => {
            console.error('Error fetching calling processes:', error);
            const tbody = document.getElementById('process-table-body');
            if (tbody) {
                tbody.innerHTML = '<tr><td colspan="5" class="error">Error loading calling processes.</td></tr>';
            }
        });
}

// Function to display calling processes in the table
function displayCallingProcesses(processes) {
    const tbody = document.getElementById('process-table-body');
    if (!tbody) return;
    
    if (processes.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="no-data">No callings currently in process.</td></tr>';
        return;
    }
    
    tbody.innerHTML = processes.map(process => `
        <tr>
            <td>${process.member_name}</td>
            <td>${process.calling_name}</td>
            <td>${formatDate(process.proposed_date)}</td>
            <td>${createProgressIndicator(process.status)}</td>
            <td>
                <div class="process-actions">
                    ${createActionButtons(process)}
                </div>
            </td>
        </tr>
    `).join('');
    
    // Add event listeners for action buttons
    addProcessActionListeners();
}

// Function to create visual progress indicator
function createProgressIndicator(status) {
    const steps = ['approved', 'interviewed', 'sustained', 'set_apart'];
    const stepLabels = ['Approved', 'Interviewed', 'Sustained', 'Set Apart'];
    const currentIndex = steps.indexOf(status);
    
    return `
        <div class="progress-indicator">
            ${steps.map((step, index) => `
                <div class="progress-step ${index <= currentIndex ? 'completed' : 'pending'}">
                    <div class="step-icon">${index <= currentIndex ? '✓' : '○'}</div>
                    <div class="step-label">${stepLabels[index]}</div>
                </div>
            `).join('<div class="step-connector"></div>')}
        </div>
    `;
}

// Function to create action buttons based on current status
function createActionButtons(process) {
    const nextActions = {
        'approved': 'Mark Interviewed',
        'interviewed': 'Mark Sustained',
        'sustained': 'Mark Set Apart',
        'set_apart': 'Finalize Calling'
    };
    
    const nextAction = nextActions[process.status];
    if (!nextAction) return '';
    
    return `
        <button class="action-btn save-btn process-advance-btn" 
                data-id="${process.id}" 
                data-status="${process.status}"
                data-member-id="${process.member_id}"
                data-calling-id="${process.calling_id}"
                data-member-name="${process.member_name}"
                data-calling-name="${process.calling_name}">
            ${nextAction}
        </button>
        <button class="action-btn remove-btn process-cancel-btn" 
                data-id="${process.id}">
            Cancel
        </button>
    `;
}

// Function to add event listeners to action buttons
function addProcessActionListeners() {
    // Advance buttons
    document.querySelectorAll('.process-advance-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const currentStatus = this.dataset.status;
            
            // Handle "Complete Process" differently - redirect to Assign/Release tab
            if (currentStatus === 'set_apart') {
                const processData = {
                    id: id,
                    memberId: this.dataset.memberId,
                    callingId: this.dataset.callingId,
                    memberName: this.dataset.memberName,
                    callingName: this.dataset.callingName
                };
                completeCallingProcess(processData);
            } else {
                advanceCallingProcess(id, currentStatus);
            }
        });
    });
    
    // Cancel buttons  
    document.querySelectorAll('.process-cancel-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            if (confirm('Are you sure you want to cancel this calling process?')) {
                cancelCallingProcess(id);
            }
        });
    });
}

// Function to advance a calling through the process
function advanceCallingProcess(id, currentStatus) {
    const nextStatusMap = {
        'approved': 'interviewed',
        'interviewed': 'sustained', 
        'sustained': 'set_apart'
    };
    
    const nextStatus = nextStatusMap[currentStatus];
    if (!nextStatus) return;
    
    fetch('update_calling_process.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            id: id,
            status: nextStatus,
            date: new Date().toISOString().substr(0, 10)
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            fetchCallingProcesses(); // Refresh the table
        } else {
            alert('Error updating process: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error advancing process:', error);
        alert('An error occurred while updating the process.');
    });
}

// Function to complete a calling process by redirecting to Assign/Release tab
function completeCallingProcess(processData) {
    if (confirm(`Complete the calling process for ${processData.memberName} → ${processData.callingName}?\n\nThis will take you to the Assign/Release Callings tab where you can finalize the assignment.`)) {
        // Redirect to Tab2 with pre-filled member and calling
        openTab(null, 'Tab2', { 
            memberId: processData.memberId, 
            callingId: processData.callingId,
            memberName: processData.memberName,
            callingName: processData.callingName,
            fromProcess: true,
            processId: processData.id
        });
    }
}

// Function to cancel a calling process
function cancelCallingProcess(id) {
    fetch('cancel_calling_process.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ id: id })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            fetchCallingProcesses(); // Refresh the table
        } else {
            alert('Error canceling process: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error canceling process:', error);
        alert('An error occurred while canceling the process.');
    });
}

// Function to update process statistics
function updateProcessStats(processes) {
    const stats = {
        approved: 0,
        interviewed: 0,
        sustained: 0,
        set_apart: 0
    };
    
    processes.forEach(process => {
        if (stats.hasOwnProperty(process.status)) {
            stats[process.status]++;
        }
    });
    
    document.getElementById('stat-approved').textContent = stats.approved;
    document.getElementById('stat-interviewed').textContent = stats.interviewed;
    document.getElementById('stat-sustained').textContent = stats.sustained;
    document.getElementById('stat-set-apart').textContent = stats.set_apart;
}

// Function to filter calling processes
function filterCallingProcesses() {
    const statusFilter = document.getElementById('process-status-filter');
    const searchInput = document.getElementById('process-search-input');
    
    if (!statusFilter || !searchInput) return;
    
    const selectedStatus = statusFilter.value.toLowerCase();
    const searchTerm = searchInput.value.toLowerCase().trim();
    
    let filteredData = allCallingProcessesData;
    
    // Filter by status if a status is selected
    if (selectedStatus) {
        filteredData = filteredData.filter(process => 
            process.status.toLowerCase() === selectedStatus
        );
    }
    
    // Filter by search term if search input has text
    if (searchTerm) {
        filteredData = filteredData.filter(process => 
            process.member_name.toLowerCase().includes(searchTerm) ||
            process.calling_name.toLowerCase().includes(searchTerm)
        );
    }
    
    // Display filtered results
    displayCallingProcesses(filteredData);
    updateProcessStats(filteredData);
}

// Helper function to format dates
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString();
}

// Global variables to store data for filtering
let allMembersData = [];
let allTab2MembersData = [];
let allTab2CallingsData = [];
let allCallingsData = [];
let allCallingProcessesData = [];

// Function to populate the Members dropdown
function fetchMembers(statusFilter = '') {
    const url = statusFilter ? `get_members.php?status=${encodeURIComponent(statusFilter)}` : 'get_members.php';
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            allMembersData = data; // Store all data for search filtering
            displayMembers(data);
        })
        .catch(error => console.error('Error fetching members:', error));
}

// Function to display members in the dropdown
function displayMembers(membersData) {
    const memberSelect = document.getElementById('member-form-select');
    
    // Clear existing options except the first one
    memberSelect.innerHTML = '<option value="">Select a Member</option>';
    
    membersData.forEach(member => {
        const option = document.createElement('option');
        option.value = member.member_id;
        
        // Add status indicator to the display text
        const statusBadge = getStatusBadge(member.status);
        option.textContent = `${member.first_name} ${member.last_name} ${statusBadge}`;
        
        memberSelect.appendChild(option);
    });

    // Only add the event listener once to avoid duplicates
    if (!memberSelect.hasAttribute('data-listener-added')) {
        memberSelect.addEventListener('change', function () {
            const memberId = this.value;
            const editBtn = document.getElementById('edit-btn');
            const removeBtn = document.getElementById('remove-member-btn');
            
            if (memberId) {
                fetchMemberDetails(memberId);
                fetchCallingsForMember(memberId);
                fetchPossibleCallingsForMember(memberId);
                
                // Show Edit and Remove buttons when member is selected
                if (editBtn) editBtn.style.display = 'inline-block';
                if (removeBtn) removeBtn.style.display = 'inline-block';
            } else {
                // Hide Edit and Remove buttons when no member is selected
                if (editBtn) editBtn.style.display = 'none';
                if (removeBtn) removeBtn.style.display = 'none';
            }
        });
        memberSelect.setAttribute('data-listener-added', 'true');
    }
}

// Function to filter members based on search and status
function filterMembers() {
    const searchTerm = document.getElementById('member-search-input').value.toLowerCase();
    const statusFilter = document.getElementById('status-filter-select').value;
    
    let filteredData = allMembersData;
    
    // Filter by search term
    if (searchTerm) {
        filteredData = filteredData.filter(member => 
            member.first_name.toLowerCase().includes(searchTerm) ||
            member.last_name.toLowerCase().includes(searchTerm) ||
            `${member.first_name} ${member.last_name}`.toLowerCase().includes(searchTerm)
        );
    }
    
    // Filter by status (this is already handled by the initial fetch, but we can refine it)
    if (statusFilter) {
        filteredData = filteredData.filter(member => member.status === statusFilter);
    }
    
    displayMembers(filteredData);
}

// Helper function to get status badge text
function getStatusBadge(status) {
    const badges = {
        'active': '',
        'inactive': '(Inactive)',
        'moved': '(Moved)',
        'no_calling': '(No Calling)',
        'deceased': '(Deceased)',
        'unknown': '(?)'
    };
    return badges[status] || '';
}

// Helper function to create status badge HTML
function createStatusBadgeHTML(status) {
    if (!status || status === 'active') {
        return '';
    }
    
    const statusLabels = {
        'inactive': 'Inactive',
        'moved': 'Moved',
        'no_calling': 'No Calling',
        'deceased': 'Deceased',
        'unknown': 'Unknown'
    };
    
    const label = statusLabels[status] || status;
    return `<span class="status-badge status-${status}">${label}</span>`;
}

// Function to populate the Callings dropdown
function fetchCallings() {
    fetch('get_callings.php')
        .then(response => response.json())
        .then(data => {
            allCallingsData = data; // Store all data for search filtering
            displayCallings(data);
        })
        .catch(error => console.error('Error fetching callings:', error));
}

// Function to display callings in the dropdown
function displayCallings(callingsData) {
    const callingSelect = document.getElementById('calling-form-select');
    
    // Clear existing options except the first one
    callingSelect.innerHTML = '<option value="">Select a Calling</option>';
    
    callingsData.forEach(calling => {
        const option = document.createElement('option');
        option.value = calling.calling_id;
        option.textContent = calling.calling_name;
        callingSelect.appendChild(option);
    });

    // Only add the event listener once to avoid duplicates
    if (!callingSelect.hasAttribute('data-listener-added')) {
        callingSelect.addEventListener('change', function () {
            const callingId = this.value;
            const editBtn = document.getElementById('edit-calling-btn');
            const removeBtn = document.getElementById('remove-calling-btn');
            
            if (callingId) {
                fetchCallingDetails(callingId);
                fetchMembersForCalling(callingId);
                fetchPossibleMembersForCalling(callingId);
                
                // Show Edit and Remove buttons when calling is selected
                if (editBtn) editBtn.style.display = 'inline-block';
                if (removeBtn) removeBtn.style.display = 'inline-block';
            } else {
                // Hide Edit and Remove buttons when no calling is selected
                if (editBtn) editBtn.style.display = 'none';
                if (removeBtn) removeBtn.style.display = 'none';
            }
        });
        callingSelect.setAttribute('data-listener-added', 'true');
    }
}

// Function to filter callings based on search
function filterCallings() {
    const searchTerm = document.getElementById('calling-search-input').value.toLowerCase();
    
    let filteredData = allCallingsData;
    
    // Filter by search term
    if (searchTerm) {
        filteredData = filteredData.filter(calling => 
            calling.calling_name.toLowerCase().includes(searchTerm) ||
            (calling.organization && calling.organization.toLowerCase().includes(searchTerm)) ||
            (calling.grouping && calling.grouping.toLowerCase().includes(searchTerm))
        );
    }
    
    displayCallings(filteredData);
}

// Fetch specific member details
function fetchMemberDetails(memberId) {
    fetch(`get_member_details.php?member_id=${encodeURIComponent(memberId)}`)
        .then(response => response.json())
        .then(member => {
            document.getElementById('first-name').value = member.first_name;
            document.getElementById('last-name').value = member.last_name;
            document.getElementById('gender').value = member.gender;
            document.getElementById('birthdate').value = member.birthdate;
            document.getElementById('member-status').value = member.status || 'active';
            document.getElementById('status-notes').value = member.status_notes || '';
            
            // Update status display badge
            const statusDisplay = document.getElementById('member-status-display');
            if (statusDisplay) {
                const badgeHTML = createStatusBadgeHTML(member.status || 'active');
                statusDisplay.innerHTML = badgeHTML ? `<strong>Current Status:</strong> ${badgeHTML}` : '';
            }
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
    const status = document.getElementById('status-input').value;
    const statusNotes = document.getElementById('status-notes-input').value;

    if (!firstName || !lastName || !gender || !birthdate) {
        alert("Please fill in all required fields.");
        return;
    }

    fetch('add_member.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `first_name=${encodeURIComponent(firstName)}&last_name=${encodeURIComponent(lastName)}&gender=${encodeURIComponent(gender)}&birthdate=${encodeURIComponent(birthdate)}&status=${encodeURIComponent(status)}&status_notes=${encodeURIComponent(statusNotes)}`
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
    
    // Setup validation for member form if not already done
    const memberForm = document.querySelector('#add-member-modal form');
    if (memberForm && !memberForm.hasValidator) {
        new FormValidator(memberForm)
            .setRules(ValidationRules.member)
            .enableRealTimeValidation();
        memberForm.hasValidator = true;
    }
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
    
    // Setup client-side validation for auth form
    const authValidator = new FormValidator(authForm)
        .setRules(ValidationRules.pin)
        .enableRealTimeValidation();

    authForm.addEventListener('submit', function(event) {
        event.preventDefault();

        // Clear previous messages
        authMessage.textContent = '';
        authMessage.className = '';

        const formData = new FormData(authForm);
        const submitButton = authForm.querySelector('button[type="submit"]');
        
        // Disable submit button during request
        submitButton.disabled = true;
        submitButton.textContent = 'Checking...';

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
                
                // Initialize the application with data loading
                initializeApplication();
            } else {
                // Handle different types of authentication failures
                if (data.error === 'Too many failed attempts') {
                    authMessage.textContent = data.message || 'Account temporarily locked due to too many failed attempts.';
                    authMessage.className = 'error-message locked';
                    
                    // Optionally disable the form for a period
                    if (data.locked_until) {
                        submitButton.textContent = `Locked (${data.locked_until}m)`;
                        setTimeout(() => {
                            submitButton.disabled = false;
                            submitButton.textContent = 'Submit';
                        }, data.locked_until * 60 * 1000); // Convert minutes to milliseconds
                    }
                } else if (data.attempts_remaining !== undefined) {
                    authMessage.textContent = data.message || `Invalid PIN. ${data.attempts_remaining} attempts remaining.`;
                    authMessage.className = 'warning-message';
                    
                    if (data.attempts_remaining <= 1) {
                        authMessage.className = 'error-message warning';
                    }
                } else {
                    authMessage.textContent = data.message || 'Invalid PIN. Please try again.';
                    authMessage.className = 'error-message';
                }
                
                // Clear the PIN field for security
                document.getElementById('pin').value = '';
            }
        })
        .catch(error => {
            console.error('Authentication error:', error);
            authMessage.textContent = 'Connection error. Please try again.';
            authMessage.className = 'error-message';
        })
        .finally(() => {
            // Re-enable submit button (unless locked)
            if (!submitButton.textContent.includes('Locked')) {
                submitButton.disabled = false;
                submitButton.textContent = 'Submit';
            }
        });
    });
});










