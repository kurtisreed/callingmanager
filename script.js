// Global authentication handler
function handleAuthenticationError(response) {
    if (response.status === 401) {
        alert('Your session has expired. Please log in again.');
        // Hide the main app content
        document.getElementById('app-content').style.display = 'none';
        // Show the authentication container
        document.getElementById('auth-container').style.display = 'block';
        // Clear any stored session data
        sessionStorage.clear();
        localStorage.clear();
        // Reset the PIN input
        document.getElementById('pin').value = '';
        document.getElementById('pin').focus();
        return true; // Indicates auth error was handled
    }
    return false; // No auth error
}

// Function to manually test session timeout (for testing purposes)
function testSessionTimeout() {
    fetch('create_calling.php', {
        method: 'POST',
        body: new FormData()
    }).catch(error => {
        console.log('Session timeout test completed');
    });
}

// Logout functionality
function logout() {
    fetch('logout.php', {
        method: 'POST'
    })
    .then(() => {
        // Always redirect to login regardless of response
        // Hide the main app content
        document.getElementById('app-content').style.display = 'none';
        // Show the authentication container
        document.getElementById('auth-container').style.display = 'block';
        // Clear any stored session data
        sessionStorage.clear();
        localStorage.clear();
        // Reset the PIN input
        document.getElementById('pin').value = '';
        document.getElementById('pin').focus();
    })
    .catch(error => {
        console.error('Logout error:', error);
        // Even if logout fails, still redirect to login for security
        document.getElementById('app-content').style.display = 'none';
        document.getElementById('auth-container').style.display = 'block';
        document.getElementById('pin').value = '';
        document.getElementById('pin').focus();
    });
}

// Store the original fetch function
const originalFetch = window.fetch;

// Enhanced fetch wrapper that handles authentication
function authenticatedFetch(url, options = {}) {
    return originalFetch(url, options)
        .then(response => {
            if (handleAuthenticationError(response)) {
                throw new Error('Authentication required');
            }
            return response;
        });
}

// Override the global fetch function to automatically handle authentication
window.fetch = function(url, options = {}) {
    // Only apply authentication handling to PHP endpoints (not external URLs)
    if (typeof url === 'string' && url.endsWith('.php')) {
        return authenticatedFetch(url, options);
    }
    // For non-PHP URLs, use original fetch
    return originalFetch(url, options);
};

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
    
    // Initialize Dashboard when Tab0 is opened
    if (tabName === 'Tab0') {
        loadDashboard();
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
        
        // Temporarily removed clearing logic to test if this was breaking the preview
        
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
            'Tab0': 'Dashboard',
            'Tab1': 'Callings Overview',
            'Tab2': 'Assign/Release Callings', 
            'Tab3': 'Member Information',
            'Tab4': 'Calling Information'
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
// Load default tab only after authentication
function initializeApplication() {
    
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
    
    // Set the modal title
    document.getElementById('popup-title').textContent = title + " Candidates";
    const popupTitle = document.getElementById("popup-title"); // or use querySelector if you need
    popupTitle.setAttribute("data-calling-id", callingId);
    
    // Reset search input to default
    document.getElementById('candidate-search').value = "";  // Clear search    

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
    loadCurrentCallingHolder(callingId);

    // Display the overlay and modal
    document.getElementById('popup-overlay').style.display = 'block';
    document.getElementById('popup-modal').style.display = 'block';
}

// load the comments about callings
function loadCallingComments(callingId) {
    // Check authentication before making request
    
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

// Function to apply search filter
function applyFilters() {
    const searchTerm = document.getElementById('candidate-search').value.toLowerCase();

    const filteredCandidates = allCandidates.filter(candidate => {
        // Check name search filter
        if (searchTerm) {
            // Handle both field name formats (popup uses "First Name", member info uses "first_name")
            const firstName = candidate['First Name'] || candidate.first_name || '';
            const lastName = candidate['Last Name'] || candidate.last_name || '';
            return firstName.toLowerCase().includes(searchTerm) ||
                   lastName.toLowerCase().includes(searchTerm) ||
                   `${firstName} ${lastName}`.toLowerCase().includes(searchTerm);
        }
        
        // If no search term, return all candidates
        return true;
    });

    // Update the dropdown with filtered candidates
    populateCandidateDropdown(filteredCandidates);
}


// Function to load and display current calling holder
function loadCurrentCallingHolder(callingId) {
    // Check authentication before making request
    
    fetch(`get_calling_members.php?calling_id=${encodeURIComponent(callingId)}`)
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('current-calling-holder');
            
            // Filter for active members only (those not yet released)
            const activeMembers = data.filter(member => !member.date_released);
            
            if (activeMembers.length > 0) {
                let html = '<div style="background-color: arcticblue; padding: 2px; border-radius: 5px; border: 1px solid #c3e6cb;">';
                
                if (activeMembers.length === 1) {
                    const member = activeMembers[0];
                    html += `<p style="margin: 2px;">${member.member_name}`;
                    if (member.date_set_apart) {
                        html += ` <em>(${member.date_set_apart})</em>`;
                    }
                    html += '</p>';
                } else {
                    activeMembers.forEach(member => {
                        html += `<p style="margin: 2px;">${member.member_name}`;
                        if (member.date_set_apart) {
                            html += ` <em>(${member.date_set_apart})</em>`;
                        }
                        html += '</p>';
                    });
                }
                
                html += '</div>';
                container.innerHTML = html;
            } else {
                container.innerHTML = '<div style="background-color: #fff3cd; padding: 2px; border-radius: 5px; border: 1px solid #ffeaa7;"><p style="margin: 2px;"><strong>This calling is currently vacant</strong></p></div>';
            }
        })
        .catch(error => {
            console.error('Error loading current calling holder:', error);
            document.getElementById('current-calling-holder').innerHTML = '<p style="color: red;">Error loading current calling holder information.</p>';
        });
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
            
            // After loading all candidates, check for approval status
            checkApprovalStatus(newCalling);
        })
        .catch(error => {
            console.error('Error fetching possible callings:', error);
        });
}

// Function to check approval status and update visual state
function checkApprovalStatus(callingId) {
    fetch('check_approval_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `calling_id=${encodeURIComponent(callingId)}`
    })
    .then(response => response.json())
    .then(approvedCandidates => {
        if (approvedCandidates.error) {
            console.error('Error checking approval status:', approvedCandidates.message);
            return;
        }
        
        // Update visual state for approved candidates
        approvedCandidates.forEach(candidate => {
            updateCandidateToApproved(candidate.member_id, candidate.possible_callings_id);
        });
    })
    .catch(error => {
        console.error('Error fetching approval status:', error);
    });
}

// Function to update a candidate's visual state to approved
function updateCandidateToApproved(memberId, possibleCallingsId) {
    const selectionList = document.getElementById('selectionList');
    const candidateItems = selectionList.querySelectorAll('.selected-item');
    
    candidateItems.forEach(item => {
        const itemMemberId = item.getAttribute('data-member-id');
        const itemPossibleCallingsId = item.getAttribute('data-possible-callings-id');
        
        if (itemMemberId === memberId.toString() && 
            itemPossibleCallingsId === possibleCallingsId.toString()) {
            
            // Change background to light green
            item.style.backgroundColor = '#d4edda';
            item.style.border = '1px solid #c3e6cb';
            item.style.padding = '8px';
            item.style.borderRadius = '4px';
            
            // Find and update the checkmark to show approved status
            const checkButton = item.querySelector('.check-btn');
            if (checkButton) {
                checkButton.textContent = '✔ APPROVED - IN PROCESS';
                checkButton.style.color = '#28a745';
                checkButton.style.fontWeight = 'bold';
                checkButton.style.cursor = 'default';
                
                // Remove click event listener
                checkButton.replaceWith(checkButton.cloneNode(true));
            }
        }
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
            alert(`✓ ${result.message}\n\nThe calling can be managed in the Dashboard.`);
            closePopup();

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
                    isApproved: details.is_approved,
                    considering: details.considering,
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
            
            // --- Step 3: Load saved order and sort groupings accordingly ---
            loadSavedOrderAndRender(groupedData);
        })
        .catch(error => {
            console.error('Error loading callings overview:', error);
            largeBox.innerHTML = `<p style="color: red;">Error loading data. Please try again. (${error.message})</p>`;
        });
}
function initializeDragAndDrop() {
    const largeBox = document.getElementById('large-box');
    
    // Initialize SortableJS on the container
    if (largeBox && typeof Sortable !== 'undefined') {
        Sortable.create(largeBox, {
            animation: 150,
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            dragClass: 'sortable-drag',
            handle: '.box-header', // Only allow dragging by the header
            onStart: function(evt) {
                // Add visual feedback when dragging starts
                evt.item.classList.add('dragging');
            },
            onEnd: function(evt) {
                // Remove visual feedback when dragging ends
                evt.item.classList.remove('dragging');
                
                // Save the new order if the position changed
                if (evt.oldIndex !== evt.newIndex) {
                    saveBoxOrder();
                }
            }
        });
    }
}

// Save the current order of boxes to the backend
function saveBoxOrder() {
    const largeBox = document.getElementById('large-box');
    const boxes = largeBox.querySelectorAll('.small-box');
    
    // Extract the grouping names in their current order
    const orderData = Array.from(boxes).map((box, index) => {
        const header = box.querySelector('.box-header');
        return {
            grouping: header.textContent.trim(),
            order: index + 1
        };
    });
    
    // Send the order data to the backend
    fetch('save_calling_order.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ order: orderData })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Calling order saved successfully');
        } else {
            console.error('Failed to save calling order:', data.message);
        }
    })
    .catch(error => {
        console.error('Error saving calling order:', error);
    });
}

// Function to load saved order and render the boxes accordingly
function loadSavedOrderAndRender(groupedData) {
    const largeBox = document.getElementById('large-box');
    
    // First, render all boxes in default order
    renderBoxes(groupedData);
    
    // Then fetch saved order and rearrange if needed
    fetch('get_calling_order.php')
        .then(response => response.json())
        .then(orderData => {
            if (orderData.success && orderData.order && orderData.order.length > 0) {
                rearrangeBoxesByOrder(orderData.order);
            }
            
            // Initialize drag-and-drop after rendering
            setTimeout(() => {
                attachPopupListeners();
                initializeDragAndDrop();
            }, 100);
        })
        .catch(error => {
            console.log('No saved order found, using default order');
            // Initialize drag-and-drop with default order
            setTimeout(() => {
                attachPopupListeners();
                initializeDragAndDrop();
            }, 100);
        });
}

// Function to render boxes in their default order
function renderBoxes(groupedData) {
    const largeBox = document.getElementById('large-box');
    largeBox.innerHTML = ''; // Clear loading message

    for (const groupingName in groupedData) {
        // Get the entire group object, which contains 'organization' and 'callings'
        const groupInfo = groupedData[groupingName]; 
        
        // Get the array of callings from the group object
        const callingsInGroup = groupInfo.callings; 

        let groupContentHtml = callingsInGroup.map(calling => {
            const membersHtml = calling.members.map(member =>
                `<div data-member-id="${member.member_id}">     - ${member.first_name} ${member.last_name} (${member.date_set_apart})</div>`
            ).join('') || `<div style="font-style: italic;color: red;">     - (Vacant)</div>`;

            let indicatorHtml = '';
            if (calling.isApproved) {
                indicatorHtml = '<span class="checkmark-symbol">⚙</span>';
            } else if (calling.isConsidered) {
                indicatorHtml = '<span class="delta-symbol">👤</span>';
            }

            return `
                <div class="box-title" data-calling-id="${calling.callingId}" data-title="${calling.callingName}">
                    ${indicatorHtml} <span class="calling-name ${calling.considering ? 'considering' : ''}">${calling.callingName}</span>
                    <input type="checkbox" class="considering-checkbox" data-calling-id="${calling.callingId}" ${calling.considering ? 'checked' : ''} onchange="toggleCallingConsidering(this, event)" onclick="event.stopPropagation()">
                </div>
                <div class="box-content">
                    ${membersHtml}
                </div>
            `;
        }).join('');

        // Now this line will work because groupInfo.organization is correctly defined
        const orgClassName = groupInfo.organization.replace(/[^a-zA-Z0-9]/g, '');

        const boxHtml = `
            <div class="small-box ${orgClassName}" data-grouping="${groupingName}">
                <div class="box-header">${groupingName}</div>
                <div class="box-content-wrapper" style="padding: 10px;">
                    ${groupContentHtml}
                </div>
            </div>
        `;

        largeBox.insertAdjacentHTML('beforeend', boxHtml);
    }
}

// Function to rearrange boxes based on saved order
function rearrangeBoxesByOrder(orderData) {
    const largeBox = document.getElementById('large-box');
    const boxes = Array.from(largeBox.querySelectorAll('.small-box'));
    
    // Create order map
    const orderMap = {};
    orderData.forEach(item => {
        orderMap[item.grouping] = item.order_position;
    });
    
    // Sort boxes by saved order
    boxes.sort((a, b) => {
        const groupingA = a.dataset.grouping;
        const groupingB = b.dataset.grouping;
        const orderA = orderMap[groupingA] || 999;
        const orderB = orderMap[groupingB] || 999;
        return orderA - orderB;
    });
    
    // Clear and re-append in correct order
    largeBox.innerHTML = '';
    boxes.forEach(box => largeBox.appendChild(box));
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

            // Filter to show only current callings (no release date)
            const currentCallings = data.filter(calling => !calling.date_released);

            if (currentCallings.length > 0) {
                let heading = '<label>Current Callings:</label>'; // Changed from "Calling History"

                let table = '<table>';
                table += `
                    <tr>
                        <th>Status</th>
                        <th>Calling Name</th>
                        <th>Date Started</th>
                    </tr>`;

                currentCallings.forEach(calling => {
                    table += `
                        <tr>
                            <td>Active</td>
                            <td>${calling.calling_name}</td>
                            <td>${calling.date_set_apart}</td>
                        </tr>
                    `;
                });

                table += '</table>';
                container.innerHTML = heading + table;
            } else {
                container.innerHTML = '<label>Current Callings:</label><p>No current callings for this member.</p>';
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

function fetchCallingHistoryForMember(memberId) {
    fetch(`get_calling_history.php?member_id=${encodeURIComponent(memberId)}`)
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('member-calling-history');
            container.innerHTML = ''; // Clear previous content

            if (data.length > 0) {
                let heading = '<label>Calling History:</label>';

                let table = '<table>';
                table += `
                    <tr>
                        <th>Calling Name</th>
                        <th>Period Served</th>
                        <th>Notes</th>
                    </tr>`;

                data.forEach(history => {
                    table += `
                        <tr>
                            <td>${history.calling_name}</td>
                            <td>${history.approximate_period || '—'}</td>
                            <td>${history.notes || '—'}</td>
                        </tr>
                    `;
                });

                table += '</table>';
                container.innerHTML = heading + table;
            } else {
                container.innerHTML = '<label>Calling History:</label><p>No calling history records for this member.</p>';
            }
        })
        .catch(error => {
            console.error('Error fetching calling history:', error);
            document.getElementById('member-calling-history').innerHTML = '<p>Error loading calling history.</p>';
        });
}




// This event listener is now moved inside DOMContentLoaded below

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
                        <h3>Select Member</h3>
                    </div>
                    <div class="search-section">
                        <div class="search-column">
                            <div class="search-field">
                                <label for="member-search-input">Search Members:</label>
                                <input type="text" id="member-search-input" placeholder="Type name to search..." />
                            </div>
                        </div>
                    </div>
                    <div class="selection-section">
                        <label for="member-form-select">Select Member:</label>
                        <select id="member-form-select">
                            <option value="">Select a Member</option>
                        </select>
                    </div>

                    <!-- Action Buttons Section -->
                    <div class="section-header">
                        <h3>Actions</h3>
                    </div>
                    <div class="action-buttons">
                        <button id="add-member-btn" type="button" class="action-btn save-btn" onclick="openAddMemberModal()">+ Add New Member</button>
                        <button id="update-members-btn" type="button" class="action-btn edit-btn" onclick="openUpdateMembersModal()">Update Members from PDF</button>
                        <button type="button" id="edit-btn" class="action-btn edit-btn" style="display: none;">Edit Member</button>
                        <button type="button" id="add-calling-history-btn" class="action-btn edit-btn" style="display: none;" onclick="openAddCallingHistoryModal()">Add Calling History</button>
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
                        <h3>Member Details</h3>
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
                    <div id="member-calling-history"></div>
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
    
    // Add event listeners for search
    const searchInput = document.getElementById('member-search-input');
    
    // Real-time search as user types
    searchInput.addEventListener('input', function() {
        filterMembers();
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
                        <h3>Select Calling</h3>
                    </div>
                    <div class="search-section">
                        <div class="search-column">
                            <div class="search-field">
                                <label for="calling-search-input">Search Callings:</label>
                                <input type="text" id="calling-search-input" placeholder="Type calling name to search..." />
                            </div>
                        </div>
                    </div>
                    <div class="selection-section">
                        <label for="calling-form-select">Select Calling:</label>
                        <select id="calling-form-select">
                            <option value="">Select a Calling</option>
                        </select>
                    </div>

                    <!-- Action Buttons Section -->
                    <div class="section-header">
                        <h3>Actions</h3>
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
                        <h3>Calling Details</h3>
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


// Function to fetch calling processes from server (for action refreshes)
function fetchCallingProcesses() {
    fetch('get_calling_processes.php')
        .then(response => response.json())
        .then(data => {
            allCallingProcessesData = data; // Store data for filtering
            
            // If we're currently showing a process detail view, update it
            const activeProcessStat = document.querySelector('.clickable-process-stat.active-stat');
            if (activeProcessStat) {
                const statusType = activeProcessStat.dataset.processStatus;
                displayCallingProcessTable(data, statusType);
            }
            
            // Update both dashboard stats and old-style stats
            updateDashboardProcessStats(data);
            updateProcessStats(data);
        })
        .catch(error => {
            console.error('Error fetching calling processes:', error);
        });
}


// Function to create visual progress indicator
function createProgressIndicator(process) {
    const steps = ['approved', 'interviewed', 'sustained', 'set_apart'];
    const stepLabels = ['Approved', 'Interviewed', 'Sustained', 'Set Apart'];
    const dateFields = ['approved_date', 'interviewed_date', 'sustained_date', 'set_apart_date'];
    const currentIndex = steps.indexOf(process.status);

    return `
        <div class="progress-indicator">
            ${steps.map((step, index) => {
                const isCompleted = index <= currentIndex;
                const stepDate = process[dateFields[index]];
                const formattedDate = stepDate ? formatDate(stepDate) : '';

                return `
                    <div class="progress-step ${isCompleted ? 'completed' : 'pending'}">
                        <div class="step-icon">${isCompleted ? '✓' : '○'}</div>
                        <div class="step-label">${stepLabels[index]}</div>
                        ${formattedDate ? `<div class="step-date editable-date"
                                              data-process-id="${process.id}"
                                              data-date-field="${dateFields[index]}"
                                              data-current-date="${stepDate}"
                                              data-step-status="${steps[index]}"
                                              data-current-process-status="${process.status}"
                                              data-step-label="${stepLabels[index]}"
                                              title="Click to edit date">${formattedDate}</div>` : ''}
                    </div>
                `;
            }).join('<div class="step-connector"></div>')}
        </div>
    `;
}

// Function to create action buttons based on current status
function createActionButtons(process) {
    const nextActions = {
        'approved': 'Mark Interviewed',
        'interviewed': 'Mark Sustained',
        'sustained': 'Mark Set Apart'
        // Removed 'set_apart': 'Finalize Calling' from normal progression
    };
    
    let buttons = '';
    
    // Add progression button (up to Set Apart)
    const nextAction = nextActions[process.status];
    if (nextAction) {
        buttons += `
            <button class="action-btn save-btn process-advance-btn"
                    data-id="${process.id}"
                    data-status="${process.status}"
                    data-member-id="${process.member_id}"
                    data-calling-id="${process.calling_id}"
                    data-member-name="${process.member_name}"
                    data-calling-name="${process.calling_name}">
                ${nextAction}
            </button>`;
    }
    
    // Add Finalize Calling button (only if all progression steps are completed)
    const allStepsCompleted = process.approved_date && process.interviewed_date &&
                             process.sustained_date && process.set_apart_date;
    
    if (allStepsCompleted) {
        buttons += `
            <button class="action-btn save-btn process-finalize-btn" 
                    data-id="${process.id}">
                Finalize Calling
            </button>`;
    }
    
    // Add Cancel button
    buttons += `
        <button class="action-btn remove-btn process-cancel-btn" 
                data-id="${process.id}">
            Cancel
        </button>`;
    
    return buttons;
}

// Function to add event listeners to action buttons
function addProcessActionListeners() {
    // Advance buttons - special handling for "Mark Sustained" to update then redirect to Tab2
    document.querySelectorAll('.process-advance-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const currentStatus = this.dataset.status;

            // If moving from interviewed to sustained, update status then redirect to Assign/Release tab
            if (currentStatus === 'interviewed') {
                const processData = {
                    id: id,
                    memberId: this.dataset.memberId,
                    callingId: this.dataset.callingId,
                    memberName: this.dataset.memberName,
                    callingName: this.dataset.callingName
                };
                advanceToSustainedAndActivate(processData);
            } else {
                // Otherwise, proceed with normal advancement
                advanceCallingProcess(id, currentStatus);
            }
        });
    });
    
    // Finalize Calling buttons - only removes from calling_process table
    document.querySelectorAll('.process-finalize-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            if (confirm('Are you sure you want to finalize this calling?')) {
                finalizeCallingProcess(id);
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

    // Editable date click handlers
    document.querySelectorAll('.editable-date').forEach(dateEl => {
        dateEl.addEventListener('click', function() {
            const processId = this.dataset.processId;
            const dateField = this.dataset.dateField;
            const currentDate = this.dataset.currentDate;
            const stepStatus = this.dataset.stepStatus;
            const currentProcessStatus = this.dataset.currentProcessStatus;
            const stepLabel = this.dataset.stepLabel;
            editProcessDate(processId, dateField, currentDate, stepStatus, currentProcessStatus, stepLabel);
        });
    });
}

// Function to edit a process date
function editProcessDate(processId, dateField, currentDate, stepStatus, currentProcessStatus, stepLabel) {
    // Prompt user for new date
    const newDate = prompt(`Edit date for ${stepLabel}:\n(Format: YYYY-MM-DD)`, currentDate);

    if (newDate === null) {
        // User cancelled
        return;
    }

    // Validate date format
    if (!newDate.match(/^\d{4}-\d{2}-\d{2}$/)) {
        alert('Invalid date format. Please use YYYY-MM-DD format.');
        return;
    }

    // Update the date - use stepStatus for which date field to update,
    // but currentProcessStatus to preserve the actual process status
    fetch('update_calling_process.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            id: processId,
            status: stepStatus,
            date: newDate,
            preserve_status: true, // Flag to tell PHP not to change the actual status
            actual_status: currentProcessStatus // Pass the actual current status
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Refresh the dashboard
            fetchProcessStats();

            // If we're currently showing a process detail view, refresh the table
            const activeProcessStat = document.querySelector('.clickable-process-stat.active-stat');
            if (activeProcessStat) {
                const statusType = activeProcessStat.dataset.processStatus;
                fetch('get_calling_processes.php')
                    .then(response => response.json())
                    .then(data => {
                        displayCallingProcessTable(data, statusType);
                    });
            }
        } else {
            alert('Error updating date: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error updating date:', error);
        alert('An error occurred while updating the date.');
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

// Function to open finalize modal for sustained calling
function advanceToSustainedAndActivate(processData) {
    // Just open the modal - status will be updated when user clicks Finalize
    openFinalizeCallingModal(processData);
}

// Function to activate a calling (redirect to Assign/Release tab)
function activateCalling(processData) {
    if (confirm(`Activate the calling for ${processData.memberName} → ${processData.callingName}?\n\nThis will take you to the Assign/Release Callings tab where you can make the assignment official.`)) {
        // Redirect to Tab2 with pre-filled member and calling
        openTab(null, 'Tab2', {
            memberId: processData.memberId,
            callingId: processData.callingId,
            memberName: processData.memberName,
            callingName: processData.callingName,
            fromProcess: true
        });
    }
}

// Function to open the finalize calling modal
function openFinalizeCallingModal(processData) {
    // Show modal and overlay
    document.getElementById('finalize-calling-modal').style.display = 'block';
    document.getElementById('finalize-calling-overlay').style.display = 'block';

    // Store process data globally for later use
    window.finalizeCallingData = processData;

    // Set member and calling names in header
    document.getElementById('finalize-member-name').textContent = processData.memberName;
    document.getElementById('finalize-calling-name').textContent = processData.callingName;

    // Set default date to today
    document.getElementById('finalize-date-set-apart').value = new Date().toISOString().substr(0, 10);

    // Fetch and display current information
    fetchFinalizeModalData(processData.memberId, processData.memberName, processData.callingId, processData.callingName);
}

// Function to close the finalize calling modal
function closeFinalizeCallingModal() {
    // Hide modal
    document.getElementById('finalize-calling-modal').style.display = 'none';
    document.getElementById('finalize-calling-overlay').style.display = 'none';

    // Clear the modal content
    document.getElementById('finalize-member-callings-container').innerHTML = '';
    document.getElementById('finalize-calling-members-container').innerHTML = '';
    document.getElementById('finalize-changes-preview').style.display = 'none';
    document.getElementById('finalize-remove-other-candidates-section').style.display = 'none';
    document.getElementById('finalize-form-response').textContent = '';
    document.getElementById('finalize-make-changes-btn').disabled = true;

    // Clear stored data
    window.finalizeCallingData = null;

    // Refresh the dashboard to show updated data
    fetchProcessStats();
    fetchDashboardStats();

    // If we're currently showing a process detail view, refresh the table
    const activeProcessStat = document.querySelector('.clickable-process-stat.active-stat');
    if (activeProcessStat) {
        const statusType = activeProcessStat.dataset.processStatus;
        fetch('get_calling_processes.php')
            .then(response => response.json())
            .then(data => {
                displayCallingProcessTable(data, statusType);
            });
    }

    // Also check if we're showing a general stat detail view and refresh it
    const activeGeneralStat = document.querySelector('.clickable-stat.active-stat');
    if (activeGeneralStat) {
        const statType = activeGeneralStat.dataset.stat;
        const statLabel = activeGeneralStat.querySelector('.stat-label').textContent;
        setTimeout(() => {
            showStatDetails(statType, statLabel);
        }, 100);
    }
}

// Function to fetch and display data in finalize modal
function fetchFinalizeModalData(memberId, memberName, callingId, callingName) {
    // Fetch member's current callings
    fetch(`get_member_callings.php?member_id=${encodeURIComponent(memberId)}`)
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('finalize-member-callings-container');
            container.innerHTML = '';

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
                    const isActive = !calling.date_released;

                    table += `
                        <tr>
                            <td>${isActive ? 'Active' : 'Old'}</td>
                            <td>${isActive ? `<input type="checkbox" class="finalize-release-calling-checkbox" value="${calling.id}">` : ''}</td>
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

            // Add event listeners for checkboxes
            const checkboxes = container.querySelectorAll('.finalize-release-calling-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateFinalizeChangesPreview);
            });
            updateFinalizeChangesPreview();
        });

    // Fetch calling's current members
    fetch(`get_calling_members.php?calling_id=${encodeURIComponent(callingId)}`)
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('finalize-calling-members-container');
            container.innerHTML = '';

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
                    const isActive = !member.date_released;

                    table += `
                        <tr>
                            <td>${isActive ? 'Active' : 'Old'}</td>
                            <td>${isActive ? `<input type="checkbox" class="finalize-release-member-checkbox" value="${member.id}">` : ''}</td>
                            <td>${member.member_name}</td>
                            <td>${member.date_set_apart}</td>
                            <td>${member.date_released ? member.date_released : '—'}</td>
                        </tr>
                    `;
                });

                table += '</table>';
                content += table;
            } else {
                content += '<p>No members currently serving in this calling.</p>';
            }

            container.innerHTML = content;

            // Add event listeners for checkboxes
            const checkboxes = container.querySelectorAll('.finalize-release-member-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateFinalizeChangesPreview);
            });
            updateFinalizeChangesPreview();
        });

    // Fetch other candidates for this calling
    fetchFinalizeCandidates(callingId);
}

// Function to fetch other candidates for finalize modal
function fetchFinalizeCandidates(callingId) {
    fetch(`get_calling_candidates.php?calling_id=${encodeURIComponent(callingId)}`)
        .then(response => response.json())
        .then(data => {
            if (data.length > 0) {
                const section = document.getElementById('finalize-remove-other-candidates-section');
                const list = document.getElementById('finalize-other-candidates-list');

                list.innerHTML = data.map(candidate => candidate.member_name).join(', ');
                section.style.display = 'block';

                // Add event listener to checkbox
                document.getElementById('finalize-remove-other-candidates-checkbox').addEventListener('change', updateFinalizeChangesPreview);
            } else {
                document.getElementById('finalize-remove-other-candidates-section').style.display = 'none';
            }
        });
}

// Function to update changes preview in finalize modal
function updateFinalizeChangesPreview() {
    const processData = window.finalizeCallingData;
    if (!processData) return;

    const previewDiv = document.getElementById('finalize-changes-preview');
    const changesText = document.getElementById('finalize-changes-text');
    const changes = [];

    // Get member releases
    const memberReleaseCheckboxes = document.querySelectorAll('.finalize-release-calling-checkbox:checked');
    const memberReleases = [];
    memberReleaseCheckboxes.forEach(checkbox => {
        const row = checkbox.closest('tr');
        const callingName = row.cells[2].textContent;
        memberReleases.push(callingName);
    });

    // Get calling releases
    const callingReleaseCheckboxes = document.querySelectorAll('.finalize-release-member-checkbox:checked');
    const callingReleases = [];
    callingReleaseCheckboxes.forEach(checkbox => {
        const row = checkbox.closest('tr');
        const memberName = row.cells[2].textContent;
        callingReleases.push(memberName);
    });

    // Build changes text
    if (memberReleases.length > 0) {
        const releasesText = memberReleases.length === 1 ?
            memberReleases[0] :
            memberReleases.slice(0, -1).join(', ') + ' and ' + memberReleases.slice(-1);
        changes.push(`${processData.memberName} will be released from ${releasesText}`);
    }

    changes.push(`${processData.memberName} will be assigned to ${processData.callingName}`);

    if (callingReleases.length > 0) {
        callingReleases.forEach(memberName => {
            changes.push(`${memberName} will be released from ${processData.callingName}`);
        });
    }

    // Check if removing other candidates
    const removeOthersCheckbox = document.getElementById('finalize-remove-other-candidates-checkbox');
    if (removeOthersCheckbox && removeOthersCheckbox.checked) {
        changes.push('All other candidates will be removed from consideration for this calling');
    }

    const makeChangesBtn = document.getElementById('finalize-make-changes-btn');

    if (changes.length > 0) {
        let changeText = changes.join(', ');
        if (!changeText.endsWith('.')) {
            changeText += '.';
        }
        changeText = changeText.charAt(0).toUpperCase() + changeText.slice(1);

        changesText.textContent = changeText;
        previewDiv.style.display = 'block';

        if (makeChangesBtn) {
            makeChangesBtn.disabled = false;
        }
    } else {
        previewDiv.style.display = 'none';

        if (makeChangesBtn) {
            makeChangesBtn.disabled = true;
        }
    }
}

// Function to finalize the calling assignment from modal
function finalizeFinalizeCallingChanges() {
    const processData = window.finalizeCallingData;
    if (!processData) return;

    const dateField = document.getElementById('finalize-date-set-apart');
    const date = dateField.value;

    if (!date) {
        alert('Please select a date.');
        return;
    }

    // Get member releases
    const memberReleaseCheckboxes = document.querySelectorAll('.finalize-release-calling-checkbox:checked');
    const memberReleases = Array.from(memberReleaseCheckboxes).map(cb => cb.value);

    // Get calling releases
    const callingReleaseCheckboxes = document.querySelectorAll('.finalize-release-member-checkbox:checked');
    const callingReleases = Array.from(callingReleaseCheckboxes).map(cb => cb.value);

    // Check if removing other candidates
    const removeOthersCheckbox = document.getElementById('finalize-remove-other-candidates-checkbox');
    const removeOthers = removeOthersCheckbox ? removeOthersCheckbox.checked : false;

    // First, update the process status to sustained
    fetch('update_calling_process.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            id: processData.id,
            status: 'sustained',
            date: date
        })
    })
    .then(response => response.json())
    .then(statusData => {
        if (!statusData.success) {
            throw new Error('Failed to update status: ' + statusData.message);
        }

        // Now make the calling assignment changes
        const requestData = {
            member_id: processData.memberId,
            calling_id: processData.callingId,
            change_date: date,
            member_releases: memberReleases,
            calling_releases: callingReleases,
            remove_other_candidates: removeOthers,
            update_possible_callings: true // Update status to 'assigned' in possible_callings table
        };

        return fetch('make_calling_changes.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(requestData)
        });
    })
    .then(response => response.text())
    .then(data => {
        document.getElementById('finalize-form-response').textContent = data;

        if (data.includes('successfully')) {
            // Close modal and refresh dashboard after a short delay
            setTimeout(() => {
                closeFinalizeCallingModal();
            }, 1500);
        }
    })
    .catch(error => {
        console.error('Error making changes:', error);
        document.getElementById('finalize-form-response').textContent = 'An error occurred while making changes: ' + error.message;
    });
}

// Add event listener for finalize button when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    const finalizeBtn = document.getElementById('finalize-make-changes-btn');
    if (finalizeBtn) {
        finalizeBtn.addEventListener('click', finalizeFinalizeCallingChanges);
    }
});

// Function to finalize calling process (remove from calling_process table only)
function finalizeCallingProcess(id) {
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
            console.log('Calling process finalized successfully');
            // Refresh the calling processes data and table
            fetchProcessStats();
            
            // If we're currently showing a process detail view, refresh the table
            const activeProcessStat = document.querySelector('.clickable-process-stat.active-stat');
            if (activeProcessStat) {
                const statusType = activeProcessStat.dataset.processStatus;
                // Refresh the process data and update the table display
                fetch('get_calling_processes.php')
                    .then(response => response.json())
                    .then(processes => {
                        allCallingProcessesData = processes;
                        displayCallingProcessTable(processes, statusType);
                    })
                    .catch(error => console.error('Error refreshing process table:', error));
            }
        } else {
            console.error('Error finalizing calling process:', data.message);
            alert('Error finalizing calling process: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error finalizing calling process:', error);
        alert('Error finalizing calling process. Please try again.');
    });
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

// Function to update process statistics (for old calling process elements)
function updateProcessStats(processes) {
    const stats = {
        approved: 0,
        interviewed: 0,
        sustained: 0,
        set_apart: 0
    };

    processes.forEach(process => {
        // Count by status
        if (process.status === 'approved') stats.approved++;
        if (process.status === 'interviewed') stats.interviewed++;
        if (process.status === 'sustained') stats.sustained++;
        if (process.status === 'set_apart') stats.set_apart++;
    });
    
    // Update old-style stat elements if they exist
    const statApproved = document.getElementById('stat-approved');
    const statInterviewed = document.getElementById('stat-interviewed');
    const statSustained = document.getElementById('stat-sustained');
    const statSetApart = document.getElementById('stat-set-apart');
    
    if (statApproved) statApproved.textContent = stats.approved;
    if (statInterviewed) statInterviewed.textContent = stats.interviewed;
    if (statSustained) statSustained.textContent = stats.sustained;
    if (statSetApart) statSetApart.textContent = stats.set_apart;
}

// Helper function to format dates
function formatDate(dateString) {
    if (!dateString) return '';
    
    // If dateString is already in YYYY-MM-DD format, just reformat it
    if (dateString.match(/^\d{4}-\d{2}-\d{2}/)) {
        const [year, month, day] = dateString.split(' ')[0].split('-');
        return `${month}/${day}/${year}`;
    }
    
    // Fallback to original method if format is unexpected
    const date = new Date(dateString);
    return date.toLocaleDateString();
}

// Global variables to store data for filtering
let allMembersData = [];
let allTab2MembersData = [];
let allTab2CallingsData = [];
let allCallingsData = [];
let allCallingProcessesData = [];

// Function to load Dashboard content
function loadDashboard() {
    const container = document.getElementById('dashboard-container');
    if (!container) return;
    
    container.innerHTML = `
        <div class="two-column-layout">
            <!-- Left Column: Stats and Controls -->
            <div class="left-column">
                <!-- Callings in Process Section -->
                <div class="section-header">
                    <h3>Callings in Process</h3>
                </div>
                <div class="details-section">
                    <div id="process-stats">
                        <div class="stat-item clickable-process-stat" data-process-status="all">
                            <span class="stat-label">All:</span>
                            <span class="stat-value" id="stat-all">Loading...</span>
                        </div>
                        <div class="stat-item clickable-process-stat" data-process-status="approved">
                            <span class="stat-label">Needs interview:</span>
                            <span class="stat-value" id="stat-approved">Loading...</span>
                        </div>
                        <div class="stat-item clickable-process-stat" data-process-status="interviewed">
                            <span class="stat-label">Needs sustaining:</span>
                            <span class="stat-value" id="stat-interviewed">Loading...</span>
                        </div>
                        <div class="stat-item clickable-process-stat" data-process-status="sustained">
                            <span class="stat-label">Needs set apart:</span>
                            <span class="stat-value" id="stat-sustained">Loading...</span>
                        </div>
                        <div class="stat-item clickable-process-stat" data-process-status="set_apart">
                            <span class="stat-label">Ready to finalize:</span>
                            <span class="stat-value" id="stat-set-apart">Loading...</span>
                        </div>
                    </div>
                </div>

                <!-- Quick Stats Section -->
                <div class="section-header">
                    <h3>Useful Information</h3>
                </div>
                <div class="details-section">
                    <div id="dashboard-stats">
                        <div class="stat-item clickable-stat" data-stat="callings-under-consideration">
                            <span class="stat-label">Callings under consideration:</span>
                            <span class="stat-value" id="stat-callings-under-consideration">Loading...</span>
                        </div>
                        <div class="stat-item clickable-stat" data-stat="members-with-callings">
                            <span class="stat-label">Members with Callings:</span>
                            <span class="stat-value" id="stat-members-with-callings">Loading...</span>
                        </div>
                        <div class="stat-item clickable-stat" data-stat="adults-without-callings">
                            <span class="stat-label">Adults without Callings:</span>
                            <span class="stat-value" id="stat-members-without-callings">Loading...</span>
                        </div>
                        <div class="stat-item clickable-stat" data-stat="members-stake-callings">
                            <span class="stat-label">Members with Stake Callings:</span>
                            <span class="stat-value" id="stat-members-stake-callings">Loading...</span>
                        </div>
                        <div class="stat-item clickable-stat" data-stat="members-multiple-callings">
                            <span class="stat-label">Members with more than 1 calling:</span>
                            <span class="stat-value" id="stat-members-multiple-callings">Loading...</span>
                        </div>
                        <div class="stat-item clickable-stat" data-stat="vacant-callings">
                            <span class="stat-label">Vacant Callings:</span>
                            <span class="stat-value" id="stat-vacant-callings">Loading...</span>
                        </div>
                        <div class="stat-item clickable-stat" data-stat="missionaries">
                            <span class="stat-label">Missionaries:</span>
                            <span class="stat-value" id="stat-missionaries">Loading...</span>
                        </div>
                        <div class="stat-item clickable-stat" data-stat="recent-calling-changes">
                            <span class="stat-label">Recent Calling Changes:</span>
                            <span class="stat-value" id="stat-recent-calling-changes">-</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Column: Detail Display -->
            <div class="right-column">
                <div class="section-header">
                    <h3 id="detail-header">Overview</h3>
                </div>
                <div class="details-section">
                    <div id="dashboard-detail-content">
                        <p>Click an item on the left to see detailed information.</p>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Load the stats data
    fetchDashboardStats();
    fetchProcessStats();
    
    // Add click event listeners to stats
    addDashboardStatClickHandlers();
    addProcessStatClickHandlers();
    
    // Automatically load "Callings in Process - All" details
    setTimeout(() => {
        showProcessDetails('all', 'All');
    }, 100); // Small delay to ensure DOM is ready
}

// Function to fetch dashboard statistics
function fetchDashboardStats() {
    fetch('get_dashboard_stats.php')
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                throw new Error(data.message);
            }
            updateDashboardStats(data);
        })
        .catch(error => {
            console.error('Error fetching dashboard stats:', error);
            // Set error states
            document.getElementById('stat-callings-under-consideration').textContent = 'Error';
            document.getElementById('stat-members-with-callings').textContent = 'Error';
            document.getElementById('stat-members-without-callings').textContent = 'Error';
            document.getElementById('stat-members-stake-callings').textContent = 'Error';
            document.getElementById('stat-members-multiple-callings').textContent = 'Error';
            document.getElementById('stat-vacant-callings').textContent = 'Error';
            document.getElementById('stat-missionaries').textContent = 'Error';
        });
}

// Function to update dashboard stats display
function updateDashboardStats(data) {
    // Update the display with data from backend
    document.getElementById('stat-callings-under-consideration').textContent = data.callings_under_consideration || 0;
    document.getElementById('stat-members-with-callings').textContent = data.members_with_callings || 0;
    document.getElementById('stat-members-without-callings').textContent = data.adults_without_callings || 0;
    document.getElementById('stat-members-stake-callings').textContent = data.members_with_stake_callings || 0;
    document.getElementById('stat-members-multiple-callings').textContent = data.members_with_multiple_callings || 0;
    document.getElementById('stat-vacant-callings').textContent = data.vacant_callings || 0;
    document.getElementById('stat-missionaries').textContent = data.missionaries || 0;
}

// Function to add click handlers to dashboard stats
function addDashboardStatClickHandlers() {
    const clickableStats = document.querySelectorAll('.clickable-stat');
    clickableStats.forEach(stat => {
        stat.addEventListener('click', function() {
            const statType = this.dataset.stat;
            const statLabel = this.querySelector('.stat-label').textContent;
            showStatDetails(statType, statLabel);
        });
    });
}

// Function to fetch process statistics
function fetchProcessStats() {
    fetch('get_calling_processes.php')
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                throw new Error(data.message);
            }
            updateDashboardProcessStats(data);
        })
        .catch(error => {
            console.error('Error fetching process stats:', error);
            // Set error states
            document.getElementById('stat-all').textContent = 'Error';
            document.getElementById('stat-approved').textContent = 'Error';
            document.getElementById('stat-interviewed').textContent = 'Error';
            document.getElementById('stat-sustained').textContent = 'Error';
            document.getElementById('stat-set-apart').textContent = 'Error';
        });
}

// Function to update process stats display
function updateDashboardProcessStats(processes) {
    const stats = {
        all: processes.length,
        approved: 0,
        interviewed: 0,
        sustained: 0,
        set_apart: 0
    };

    processes.forEach(process => {
        // Count by status
        if (process.status === 'approved') stats.approved++;
        if (process.status === 'interviewed') stats.interviewed++;
        if (process.status === 'sustained') stats.sustained++;
        if (process.status === 'set_apart') stats.set_apart++;
    });
    
    document.getElementById('stat-all').textContent = stats.all;
    document.getElementById('stat-approved').textContent = stats.approved;
    document.getElementById('stat-interviewed').textContent = stats.interviewed;
    document.getElementById('stat-sustained').textContent = stats.sustained;
    document.getElementById('stat-set-apart').textContent = stats.set_apart;
}

// Function to add click handlers to process stats
function addProcessStatClickHandlers() {
    const clickableStats = document.querySelectorAll('.clickable-process-stat');
    clickableStats.forEach(stat => {
        stat.addEventListener('click', function() {
            const statusType = this.dataset.processStatus;
            const statLabel = this.querySelector('.stat-label').textContent;
            showProcessDetails(statusType, statLabel);
        });
    });
}

// Function to show detailed process information for a selected status
function showProcessDetails(statusType, statLabel) {
    // Update header
    document.getElementById('detail-header').textContent = `Callings in Process - ${statLabel}`;
    
    // Show loading state
    document.getElementById('dashboard-detail-content').innerHTML = '<p>Loading calling processes...</p>';
    
    // Remove active class from all stats and add to clicked one
    document.querySelectorAll('.clickable-stat').forEach(s => s.classList.remove('active-stat'));
    document.querySelectorAll('.clickable-process-stat').forEach(s => s.classList.remove('active-stat'));
    document.querySelector(`[data-process-status="${statusType}"]`).classList.add('active-stat');
    
    // Fetch and display calling process data
    fetch('get_calling_processes.php')
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                throw new Error(data.message);
            }
            allCallingProcessesData = data; // Store data for action buttons
            displayCallingProcessTable(data, statusType);
        })
        .catch(error => {
            console.error('Error fetching calling processes:', error);
            document.getElementById('dashboard-detail-content').innerHTML = 
                '<p class="error">Error loading calling processes. Please try again.</p>';
        });
}

// Function to display calling process table in dashboard
function displayCallingProcessTable(processes, statusFilter) {
    // Filter processes based on status
    let filteredProcesses = processes;
    if (statusFilter && statusFilter !== 'all') {
        // Filter by status
        filteredProcesses = processes.filter(process => process.status === statusFilter);
    }

    // Sort alphabetically by last name
    filteredProcesses.sort((a, b) => {
        const aLastName = a.member_name.split(' ').pop().toLowerCase();
        const bLastName = b.member_name.split(' ').pop().toLowerCase();
        return aLastName.localeCompare(bLastName);
    });

    const container = document.getElementById('dashboard-detail-content');

    if (filteredProcesses.length === 0) {
        container.innerHTML = '<p>None</p>';
        return;
    }
    
    // Generate table HTML
    const tableHTML = `
        <table class="detail-table process-table">
            <thead>
                <tr>
                    <th>Member</th>
                    <th>Proposed Calling</th>
                    <th>Progress</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                ${filteredProcesses.map(process => `
                    <tr>
                        <td>${process.member_name}</td>
                        <td>${process.calling_name}</td>
                        <td>${createProgressIndicator(process)}</td>
                        <td>
                            <div class="process-actions">
                                ${createActionButtons(process)}
                            </div>
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
    
    container.innerHTML = tableHTML;
    
    // Add event listeners for action buttons
    addProcessActionListeners();
}

// Function to show detailed information for a selected stat
function showStatDetails(statType, statLabel) {
    // Update header
    document.getElementById('detail-header').textContent = `${statLabel}`;
    
    // Remove active class from all stats and add to clicked one
    document.querySelectorAll('.clickable-stat').forEach(s => s.classList.remove('active-stat'));
    document.querySelectorAll('.clickable-process-stat').forEach(s => s.classList.remove('active-stat'));
    document.querySelector(`[data-stat="${statType}"]`).classList.add('active-stat');
    
    // Handle special case for recent calling changes
    if (statType === 'recent-calling-changes') {
        showRecentCallingChanges();
        return;
    }
    
    // Show loading state
    document.getElementById('dashboard-detail-content').innerHTML = '<p>Loading details...</p>';
    
    // Fetch detailed data
    fetch(`get_${statType.replace(/-/g, '_')}_details.php`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                throw new Error(data.message);
            }
            displayStatDetails(data, statType);
        })
        .catch(error => {
            console.error('Error fetching stat details:', error);
            document.getElementById('dashboard-detail-content').innerHTML = 
                '<p class="error">Error loading details. Please try again.</p>';
        });
}

// Function to show recent calling changes with time period dropdown
function showRecentCallingChanges() {
    document.getElementById('dashboard-detail-content').innerHTML = `
        <div class="time-period-selector">
            <label for="time-period-select">Time Period:</label>
            <select id="time-period-select" onchange="loadRecentCallingChanges()">
                <option value="1">Last 1 Month</option>
                <option value="3">Last 3 Months</option>
                <option value="6">Last 6 Months</option>
                <option value="12">Last 12 Months</option>
            </select>
        </div>
        <div id="calling-changes-table-container">
            <p>Loading recent calling changes...</p>
        </div>
    `;
    
    // Load default data (1 month)
    loadRecentCallingChanges();
}

// Function to load recent calling changes based on selected time period
function loadRecentCallingChanges() {
    const timeSelect = document.getElementById('time-period-select');
    const period = timeSelect ? timeSelect.value : '1';
    const container = document.getElementById('calling-changes-table-container');
    
    container.innerHTML = '<p>Loading recent calling changes...</p>';
    
    fetch(`get_recent_calling_changes.php?period=${period}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                throw new Error(data.message);
            }
            displayRecentCallingChanges(data);
        })
        .catch(error => {
            console.error('Error fetching recent calling changes:', error);
            container.innerHTML = '<p class="error">Error loading recent calling changes. Please try again.</p>';
        });
}

// Function to display recent calling changes in a table
function displayRecentCallingChanges(data) {
    const container = document.getElementById('calling-changes-table-container');
    
    if (data.length === 0) {
        container.innerHTML = '<p>No calling changes found for the selected time period.</p>';
        return;
    }
    
    let tableHTML = `
        <table class="detail-table">
            <thead>
                <tr>
                    <th>Member</th>
                    <th>Calling</th>
                    <th>Organization</th>
                    <th>Change Type</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
    `;
    
    data.forEach(change => {
        const changeDate = new Date(change.change_date).toLocaleDateString();
        const changeTypeClass = change.change_type.toLowerCase();
        
        tableHTML += `
            <tr>
                <td>${change.member_name}</td>
                <td>${change.calling_name}</td>
                <td>${change.organization}</td>
                <td><span class="change-type ${changeTypeClass}">${change.change_type}</span></td>
                <td>${changeDate}</td>
            </tr>
        `;
    });
    
    tableHTML += `
            </tbody>
        </table>
    `;
    
    container.innerHTML = tableHTML;
}

// Function to display the detailed stat information
function displayStatDetails(data, statType) {
    const container = document.getElementById('dashboard-detail-content');
    
    if (!data || data.length === 0) {
        container.innerHTML = '<p>No items found.</p>';
        return;
    }
    
    // Generate table based on stat type
    let tableHTML = '<table class="detail-table"><thead><tr>';
    
    // Define headers based on stat type
    const headers = getDetailTableHeaders(statType);
    headers.forEach(header => {
        tableHTML += `<th>${header}</th>`;
    });
    
    tableHTML += '</tr></thead><tbody>';
    
    // Add rows
    data.forEach(item => {
        tableHTML += '<tr>';
        const values = getDetailTableValues(item, statType);
        values.forEach(value => {
            tableHTML += `<td>${value}</td>`;
        });
        tableHTML += '</tr>';
    });
    
    tableHTML += '</tbody></table>';
    container.innerHTML = tableHTML;
    
    // Add click handlers for callings under consideration and vacant callings
    if (statType === 'callings-under-consideration') {
        makeCallingsUnderConsiderationClickable(data);
    } else if (statType === 'vacant-callings') {
        makeVacantCallingsClickable(data);
    }
}

// Function to get table headers for different stat types
function getDetailTableHeaders(statType) {
    switch(statType) {
        case 'callings-under-consideration':
            return ['Calling', 'Organization', 'Candidates Being Considered'];
        case 'members-with-callings':
            return ['Member', 'Calling(s)', 'Date Set Apart'];
        case 'adults-without-callings':
            return ['Member', 'Age'];
        case 'members-stake-callings':
            return ['Member', 'Stake Calling', 'Date Set Apart'];
        case 'members-multiple-callings':
            return ['Member', 'Number of Callings', 'Callings'];
        case 'vacant-callings':
            return ['Calling', 'Organization', 'Grouping'];
        case 'missionaries':
            return ['Member', 'Mission Assignment', 'Date Started'];
        default:
            return ['Name', 'Details'];
    }
}

// Function to get table values for different stat types
function getDetailTableValues(item, statType) {
    switch(statType) {
        case 'callings-under-consideration':
            return [item.calling_name, item.organization, item.candidate_count];
        case 'members-with-callings':
            return [item.member_name, item.callings, formatDate(item.date_set_apart)];
        case 'adults-without-callings':
            return [item.member_name, item.age];
        case 'members-stake-callings':
            return [item.member_name, item.calling_name, formatDate(item.date_set_apart)];
        case 'members-multiple-callings':
            return [item.member_name, item.calling_count, item.callings];
        case 'vacant-callings':
            return [item.calling_name, item.organization, item.grouping];
        case 'missionaries':
            return [item.member_name, item.calling_name, formatDate(item.date_set_apart)];
        default:
            return [item.name || 'Unknown', item.details || 'No details'];
    }
}

// Function to make table rows clickable for callings under consideration
function makeCallingsUnderConsiderationClickable(data) {
    const rows = document.querySelectorAll('.detail-table tbody tr');
    rows.forEach((row, index) => {
        if (data[index]) {
            row.style.cursor = 'pointer';
            row.classList.add('clickable-row');
            row.addEventListener('click', function() {
                const callingName = data[index].calling_name;
                const callingId = data[index].calling_id;
                openCallingCandidatesModal(callingName, callingId);
            });
        }
    });
}

// Function to make table rows clickable for vacant callings
function makeVacantCallingsClickable(data) {
    const rows = document.querySelectorAll('.detail-table tbody tr');
    rows.forEach((row, index) => {
        if (data[index]) {
            row.style.cursor = 'pointer';
            row.classList.add('clickable-row');
            row.addEventListener('click', function() {
                const callingName = data[index].calling_name;
                const callingId = data[index].calling_id;
                openCallingCandidatesModal(callingName, callingId);
            });
        }
    });
}

// Function to open the calling candidates modal from dashboard
function openCallingCandidatesModal(callingName, callingId) {
    // Use the existing showPopup function
    showPopup(callingName, callingId);
}

// Function to populate the Members dropdown
function fetchMembers() {
    fetch('get_members.php')
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
                fetchCallingHistoryForMember(memberId);
                
                // Show Edit, Add Calling History, and Remove buttons when member is selected
                if (editBtn) editBtn.style.display = 'inline-block';
                const addCallingHistoryBtn = document.getElementById('add-calling-history-btn');
                if (addCallingHistoryBtn) addCallingHistoryBtn.style.display = 'inline-block';
                if (removeBtn) removeBtn.style.display = 'inline-block';
            } else {
                // Hide Edit, Add Calling History, and Remove buttons when no member is selected
                if (editBtn) editBtn.style.display = 'none';
                const addCallingHistoryBtn = document.getElementById('add-calling-history-btn');
                if (addCallingHistoryBtn) addCallingHistoryBtn.style.display = 'none';
                if (removeBtn) removeBtn.style.display = 'none';
            }
        });
        memberSelect.setAttribute('data-listener-added', 'true');
    }
}

// Function to filter members based on search
function filterMembers() {
    const searchTerm = document.getElementById('member-search-input').value.toLowerCase();
    
    let filteredData = allMembersData;
    
    // Filter by search term
    if (searchTerm) {
        filteredData = filteredData.filter(member => 
            member.first_name.toLowerCase().includes(searchTerm) ||
            member.last_name.toLowerCase().includes(searchTerm) ||
            `${member.first_name} ${member.last_name}`.toLowerCase().includes(searchTerm)
        );
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
    const callingName = document.getElementById('calling-name-input').value.trim();
    const callingOrganization = document.getElementById('calling-organization-input').value;
    const callingGrouping = document.getElementById('calling-grouping-input').value;
    const callingPriority = document.getElementById('calling-priority-input').value.trim();

    // Simple validation
    if (!callingName || !callingOrganization || !callingGrouping || callingPriority === '') {
        alert("Please fill in all fields.");
        return;
    }

    // Validate priority is a number
    const priority = parseInt(callingPriority);
    if (isNaN(priority) || priority < 0 || priority > 999) {
        alert("Priority must be a number between 0 and 999.");
        return;
    }

    // Submit to server
    const formData = new FormData();
    formData.append('calling_name', callingName);
    formData.append('organization', callingOrganization);
    formData.append('grouping', callingGrouping);
    formData.append('priority', priority);

    fetch('create_calling.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Calling created successfully!');
            closeAddCallingModal();
            fetchCallings(); // Refresh the calling list
        } else {
            alert('Error: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error creating calling. Please try again.');
    });
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

function openUpdateMembersModal() {
    document.getElementById('update-members-modal').style.display = 'block';
    
    // Reset the modal to initial state
    document.getElementById('member-pdf-upload').value = '';
    document.getElementById('upload-status').innerHTML = '';
    document.getElementById('upload-progress').style.display = 'none';
    document.getElementById('reconciliation-results').style.display = 'none';
    
    // Load last update information
    loadLastUpdateInfo();
}

function closeUpdateMembersModal() {
    document.getElementById('update-members-modal').style.display = 'none';
    
    // Reset form and progress indicators
    document.getElementById('member-pdf-upload').value = '';
    document.getElementById('upload-status').innerHTML = '';
    document.getElementById('upload-progress').style.display = 'none';
    document.getElementById('reconciliation-results').style.display = 'none';
    document.getElementById('progress-fill').style.width = '0%';
    document.getElementById('progress-text').textContent = 'Processing...';
}

async function loadLastUpdateInfo() {
    try {
        const response = await fetch('get_last_member_update.php');
        const data = await response.json();
        
        const lastUpdateElement = document.getElementById('last-update-info');
        
        if (data.error) {
            lastUpdateElement.innerHTML = '<span style="color: red;">❌ Error loading update info</span>';
            return;
        }
        
        if (data.has_update) {
            const update = data.last_update;
            const cssClass = update.is_recent ? 'recent-update' : '';
            
            let changesText = '';
            if (update.total_changes > 0) {
                const parts = [];
                if (update.new_members_added > 0) parts.push(`${update.new_members_added} new`);
                if (update.members_updated > 0) parts.push(`${update.members_updated} updated`);
                if (update.members_removed > 0) parts.push(`${update.members_removed} removed`);
                changesText = ` - ${parts.join(', ')}`;
            }
            
            lastUpdateElement.className = `last-update-info ${cssClass}`;
            lastUpdateElement.innerHTML = `
                📅 <strong>Last Update:</strong> ${update.formatted_date} (${update.time_since})${changesText}
            `;
        } else {
            lastUpdateElement.className = 'last-update-info no-update';
            lastUpdateElement.innerHTML = '⚠️ <strong>No updates performed yet</strong> - Upload a Member List PDF to get started';
        }
        
    } catch (error) {
        document.getElementById('last-update-info').innerHTML = '<span style="color: red;">❌ Error loading update info</span>';
    }
}

function handleMemberPDFUpload(input) {
    const file = input.files[0];
    if (!file) return;
    
    if (file.type !== 'application/pdf') {
        document.getElementById('upload-status').innerHTML = '<span style="color: red;">❌ Please select a PDF file</span>';
        input.value = '';
        return;
    }
    
    // Show progress
    document.getElementById('upload-progress').style.display = 'block';
    document.getElementById('upload-status').innerHTML = '<span style="color: blue;">📤 Processing PDF...</span>';
    
    // Process PDF client-side
    processPDFClientSide(file);
}

async function processPDFClientSide(file) {
    try {
        // Set up progress
        let progress = 0;
        const progressInterval = setInterval(() => {
            progress += Math.random() * 10;
            if (progress > 90) progress = 90;
            document.getElementById('progress-fill').style.width = progress + '%';
            document.getElementById('progress-text').textContent = `Processing... ${Math.round(progress)}%`;
        }, 300);
        
        // Configure PDF.js worker
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
        
        // Read file as array buffer
        const arrayBuffer = await file.arrayBuffer();
        
        // Load PDF document
        const pdf = await pdfjsLib.getDocument(arrayBuffer).promise;
        
        document.getElementById('upload-status').innerHTML = '<span style="color: blue;">📄 Extracting text from PDF...</span>';
        
        let allText = '';
        
        // Extract text from all pages
        for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
            const page = await pdf.getPage(pageNum);
            const textContent = await page.getTextContent();
            
            // Combine text items with proper spacing
            let pageText = '';
            let lastY = null;
            
            textContent.items.forEach(item => {
                // Add line breaks when Y position changes significantly
                if (lastY !== null && Math.abs(lastY - item.transform[5]) > 5) {
                    pageText += '\n';
                }
                pageText += item.str + ' ';
                lastY = item.transform[5];
            });
            
            allText += pageText + '\n';
        }
        
        clearInterval(progressInterval);
        document.getElementById('progress-fill').style.width = '100%';
        document.getElementById('progress-text').textContent = 'Parsing member data...';
        
        // Parse member data from extracted text
        const pdfMembers = parseTextContentClientSide(allText);
        
        document.getElementById('upload-status').innerHTML = '<span style="color: blue;">🔍 Reconciling with database...</span>';
        
        // Send parsed member data to server for reconciliation
        const reconciliationData = await reconcileWithDatabase(pdfMembers, allText);
        
        document.getElementById('upload-status').innerHTML = '<span style="color: green;">✅ PDF processed successfully</span>';
        displayReconciliationResults(reconciliationData);
        
    } catch (error) {
        document.getElementById('upload-status').innerHTML = '<span style="color: red;">❌ Failed to process PDF: ' + error.message + '</span>';
        document.getElementById('progress-fill').style.width = '0%';
        document.getElementById('progress-text').textContent = 'Error';
    }
}

function parseTextContentClientSide(text) {
    const members = [];
    const lines = text.split('\n');
    
    const debug = {
        total_lines: lines.length,
        sample_lines: lines.slice(0, 10),
        text_sample: text.substring(0, 500)
    };
    
    for (const line of lines) {
        const cleanLine = line.trim();
        if (cleanLine.length < 20 || cleanLine.includes('Name   Gender   Age')) continue; // Skip header and short lines
        
        // Pattern 1: "Last, First Middle   Gender   Age   DD MMM YYYY"
        // Example: "Adams, Annabelle Jewell   F   12   21 Nov 2012"
        let match = cleanLine.match(/^([A-Za-z\s\-\'\.]+),\s*([A-Za-z\s\-\'\.]+)\s+[MF]\s+\d+\s+(\d{1,2}\s+[A-Za-z]{3}\s+\d{4})/);
        if (match) {
            const member = extractMemberFromDateMatch(match[1].trim(), match[2].trim(), match[3]);
            if (member) {
                members.push(member);
                continue;
            }
        }
        
        // Pattern 2: Look for any line with "DD MMM YYYY" date format
        match = cleanLine.match(/(\d{1,2}\s+[A-Za-z]{3}\s+\d{4})/);
        if (match) {
            const beforeDate = cleanLine.split(match[0])[0].trim();
            
            // Try to extract name from the beginning
            const nameMatch = beforeDate.match(/^([A-Za-z\s\-\'\.]+),\s*([A-Za-z\s\-\'\.]+)/);
            if (nameMatch) {
                const member = extractMemberFromDateMatch(nameMatch[1].trim(), nameMatch[2].trim(), match[1]);
                if (member) {
                    members.push(member);
                    continue;
                }
            }
        }
        
        // Pattern 3: Fall back to MM/DD/YYYY format (original patterns)
        match = cleanLine.match(/^([A-Za-z\s\-\'\.]+),\s*([A-Za-z\s\-\'\.]+)\s+(\d{1,2}\/\d{1,2}\/\d{4})/);
        if (match) {
            const member = extractMemberFromMatch(match);
            if (member) members.push(member);
            continue;
        }
        
        // Pattern 4: Any line with MM/DD/YYYY date
        match = cleanLine.match(/(\d{1,2}\/\d{1,2}\/\d{4})/);
        if (match) {
            const beforeDate = cleanLine.replace(match[0], '').trim();
            const nameMatch = beforeDate.match(/([A-Za-z\-\'\.]+)[\s,]+([A-Za-z\s\-\'\.]+)/);
            
            if (nameMatch) {
                const birthdate = convertDateToMysqlClientSide(match[1]);
                if (birthdate && isAtLeast12ThisYear(birthdate)) {
                    const member = {
                        first_name: nameMatch[1].trim(),
                        last_name: nameMatch[2].trim(),
                        birthdate: birthdate,
                        status: 'active'
                    };
                    members.push(member);
                }
            }
        }
    }
    
    return members;
}

function extractMemberFromMatch(match) {
    const lastName = match[1].trim();
    const firstMiddle = match[2].trim();
    const birthdate = match[3];
    
    // Take first name only
    const firstName = firstMiddle.split(' ')[0];
    
    const mysqlDate = convertDateToMysqlClientSide(birthdate);
    if (mysqlDate) {
        // Check if member will be 12 or older during current calendar year
        if (isAtLeast12ThisYear(mysqlDate)) {
            return {
                first_name: firstName,
                last_name: lastName,
                birthdate: mysqlDate,
                status: 'active'
            };
        }
    }
    
    return null;
}

function extractMemberFromDateMatch(lastName, firstMiddle, dateString) {
    // Take first name only (ignore middle names)
    const firstName = firstMiddle.split(' ')[0];
    
    const mysqlDate = convertTextDateToMysql(dateString);
    if (mysqlDate) {
        // Check if member will be 12 or older during current calendar year
        if (isAtLeast12ThisYear(mysqlDate)) {
            return {
                first_name: firstName,
                last_name: lastName,
                birthdate: mysqlDate,
                status: 'active'
            };
        }
    }
    
    return null;
}

function convertDateToMysqlClientSide(dateString) {
    const formats = [
        { regex: /^(\d{1,2})\/(\d{1,2})\/(\d{4})$/, format: 'M/D/YYYY' },
        { regex: /^(\d{1,2})\/(\d{1,2})\/(\d{2})$/, format: 'M/D/YY' }
    ];
    
    for (const format of formats) {
        const match = dateString.match(format.regex);
        if (match) {
            let month = parseInt(match[1]);
            let day = parseInt(match[2]);
            let year = parseInt(match[3]);
            
            // Handle 2-digit years
            if (year < 100) {
                year += year < 30 ? 2000 : 1900;
            }
            
            // Validate date components
            if (month >= 1 && month <= 12 && day >= 1 && day <= 31) {
                return `${year}-${month.toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`;
            }
        }
    }
    
    return null;
}

function convertTextDateToMysql(dateString) {
    // Handle "DD MMM YYYY" format like "21 Nov 2012"
    const monthMap = {
        'Jan': 1, 'Feb': 2, 'Mar': 3, 'Apr': 4, 'May': 5, 'Jun': 6,
        'Jul': 7, 'Aug': 8, 'Sep': 9, 'Oct': 10, 'Nov': 11, 'Dec': 12
    };
    
    const match = dateString.trim().match(/^(\d{1,2})\s+([A-Za-z]{3})\s+(\d{4})$/);
    if (match) {
        const day = parseInt(match[1]);
        const monthAbbr = match[2];
        const year = parseInt(match[3]);
        const month = monthMap[monthAbbr];
        
        if (month && day >= 1 && day <= 31 && year >= 1900 && year <= 2100) {
            return `${year}-${month.toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`;
        }
    }
    
    return null;
}

function isAtLeast12ThisYear(birthdateString) {
    // Calculate if someone will be 12 or older during the current calendar year
    // birthdateString format: "YYYY-MM-DD"
    
    const currentYear = new Date().getFullYear();
    const birthYear = parseInt(birthdateString.split('-')[0]);
    
    // Age they will turn this year (regardless of whether birthday has passed)
    const ageThisYear = currentYear - birthYear;
    
    return ageThisYear >= 12;
}

async function reconcileWithDatabase(pdfMembers, extractedText) {
    try {
        const response = await fetch('reconcile_members.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                pdf_members: pdfMembers,
                debug_info: {
                    extraction_method: 'client_side_pdfjs',
                    extracted_text_length: extractedText.length,
                    text_sample: extractedText.substring(0, 500),
                    pdf_members_found: pdfMembers.length
                }
            })
        });
        
        return await response.json();
    } catch (error) {
        throw new Error('Failed to reconcile with database: ' + error.message);
    }
}

function displayReconciliationResults(data) {
    // Store reconciliation data for later use
    window.currentReconciliation = data.reconciliation;
    
    // Update statistics
    document.getElementById('new-members-count').textContent = data.stats.new_members;
    document.getElementById('updates-count').textContent = data.stats.updates;
    document.getElementById('no-changes-count').textContent = data.stats.no_changes;
    document.getElementById('removed-count').textContent = data.stats.removed;
    
    // Build changes review HTML
    let changesHTML = '';
    
    // Add debug section if available
    if (data.debug) {
        changesHTML += `
            <div class="changes-category">
                <h4 style="cursor: pointer;" onclick="toggleDebugInfo()">🔍 Debug Info - Click to view</h4>
                <div id="debug-info" style="display: none;">
                    <p><strong>Extraction Method:</strong> ${data.debug.extraction_method || 'Unknown'}</p>
                    ${data.debug.extraction_error ? `<p><strong>Extraction Error:</strong> ${data.debug.extraction_error}</p>` : ''}
                    ${data.debug.extracted_text_length ? `<p><strong>Text Extracted:</strong> ${data.debug.extracted_text_length} characters</p>` : ''}
                    ${data.debug.total_lines ? `<p><strong>Total Lines:</strong> ${data.debug.total_lines}</p>` : ''}
                    <p><strong>PDF Members Found:</strong> ${data.debug.pdf_members_found}</p>
                    <p><strong>Current Members in DB:</strong> ${data.debug.current_members_count}</p>
                    
                    ${data.debug.text_sample ? `
                        <p><strong>Text Sample (first 500 chars):</strong></p>
                        <pre style="background: #f5f5f5; padding: 10px; font-size: 12px; max-height: 100px; overflow-y: auto;">${data.debug.text_sample}</pre>
                    ` : ''}
                    
                    ${data.debug.sample_lines ? `
                        <p><strong>Sample Lines from PDF:</strong></p>
                        <ul style="font-size: 12px; max-height: 100px; overflow-y: auto;">
                            ${data.debug.sample_lines.map(line => `<li>${line || '(empty line)'}</li>`).join('')}
                        </ul>
                    ` : ''}
                    
                    <p><strong>Sample PDF Members:</strong></p>
                    <ul>
                        ${data.debug.pdf_sample.map(member => 
                            `<li>${member.last_name}, ${member.first_name} (${member.birthdate})</li>`
                        ).join('')}
                    </ul>
                    <p><strong>Sample Current Members:</strong></p>
                    <ul>
                        ${data.debug.current_sample.map(member => 
                            `<li>${member.last_name}, ${member.first_name} (${member.birthdate})</li>`
                        ).join('')}
                    </ul>
                </div>
            </div>
        `;
    }
    
    // New Members
    if (data.reconciliation.new_members.length > 0) {
        changesHTML += `
            <div class="changes-category">
                <h4>New Members (${data.reconciliation.new_members.length})</h4>
                <ul>`;
        data.reconciliation.new_members.forEach(member => {
            changesHTML += `<li>${member.last_name}, ${member.first_name} (DOB: ${member.birthdate})</li>`;
        });
        changesHTML += '</ul></div>';
    }
    
    // Updates
    if (data.reconciliation.updates.length > 0) {
        changesHTML += `
            <div class="changes-category">
                <h4>Updates (${data.reconciliation.updates.length})</h4>
                <ul>`;
        data.reconciliation.updates.forEach(update => {
            const current = update.current;
            const changes = update.changes;
            changesHTML += `<li>${current.last_name}, ${current.first_name} - `;
            
            const changeDescriptions = [];
            Object.keys(changes).forEach(field => {
                const change = changes[field];
                let fieldName = field.replace('_', ' ');
                fieldName = fieldName.charAt(0).toUpperCase() + fieldName.slice(1);
                changeDescriptions.push(`${fieldName}: ${change.old} → ${change.new}`);
            });
            
            changesHTML += changeDescriptions.join(', ');
            changesHTML += ` (Confidence: ${Math.round(update.confidence * 100)}%)</li>`;
        });
        changesHTML += '</ul></div>';
    }
    
    // Removed Members
    if (data.reconciliation.removed.length > 0) {
        changesHTML += `
            <div class="changes-category">
                <h4>❌ Removed Members (${data.reconciliation.removed.length})</h4>
                <ul>`;
        data.reconciliation.removed.forEach(member => {
            changesHTML += `<li>${member.last_name}, ${member.first_name} (Not found in PDF - may have moved away)</li>`;
        });
        changesHTML += '</ul></div>';
    }
    
    // No Changes (collapsed by default)
    if (data.reconciliation.no_changes.length > 0) {
        changesHTML += `
            <div class="changes-category">
                <h4 style="cursor: pointer;" onclick="toggleNoChanges()">✅ No Changes Required (${data.reconciliation.no_changes.length}) - Click to view</h4>
                <ul id="no-changes-list" style="display: none;">`;
        data.reconciliation.no_changes.forEach(member => {
            const current = member.current;
            changesHTML += `<li>${current.last_name}, ${current.first_name} (Confidence: ${Math.round(member.confidence * 100)}%)</li>`;
        });
        changesHTML += '</ul></div>';
    }
    
    document.getElementById('changes-review').innerHTML = changesHTML;
    document.getElementById('reconciliation-results').style.display = 'block';
}

function toggleNoChanges() {
    const list = document.getElementById('no-changes-list');
    list.style.display = list.style.display === 'none' ? 'block' : 'none';
}

function toggleDebugInfo() {
    const info = document.getElementById('debug-info');
    info.style.display = info.style.display === 'none' ? 'block' : 'none';
}

function showMockReconciliationResults() {
    // Keep mock function for testing
    const mockData = {
        reconciliation: {
            new_members: [
                {first_name: 'John', last_name: 'Smith', birthdate: '1985-03-15'},
                {first_name: 'Sarah', last_name: 'Johnson', birthdate: '1992-07-22'},
                {first_name: 'Michael', last_name: 'Williams', birthdate: '1978-11-08'}
            ],
            updates: [
                {
                    current: {first_name: 'Emily', last_name: 'Brown'},
                    changes: {status: {old: 'Active', new: 'Inactive'}},
                    confidence: 0.95
                }
            ],
            no_changes: [],
            removed: [
                {first_name: 'Richard', last_name: 'Thompson'},
                {first_name: 'Maria', last_name: 'Garcia'}
            ]
        },
        stats: {new_members: 3, updates: 1, no_changes: 45, removed: 2}
    };
    
    displayReconciliationResults(mockData);
}

function applyMemberChanges() {
    if (!confirm('Are you sure you want to apply all these changes to the member database?')) {
        return;
    }
    
    if (!window.currentReconciliation) {
        alert('No reconciliation data available. Please upload a PDF first.');
        return;
    }
    
    // Disable the apply button and show progress
    const applyButton = document.getElementById('apply-changes-btn');
    applyButton.disabled = true;
    applyButton.textContent = 'Applying Changes...';
    
    // Send reconciliation data to server for processing
    fetch('apply_member_changes.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            reconciliation: window.currentReconciliation
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            alert('Error applying changes: ' + data.message);
            applyButton.disabled = false;
            applyButton.textContent = 'Apply All Changes';
        } else {
            alert(`Changes applied successfully!\n\nNew members added: ${data.summary.new_members_added}\nMembers updated: ${data.summary.members_updated}\nMembers marked as removed: ${data.summary.members_removed}`);
            closeUpdateMembersModal();
            
            // Refresh member data if we're on the Member Information tab
            if (document.getElementById('Tab3').style.display !== 'none') {
                loadMembersForm();
            }
            
            // Clear stored reconciliation data
            window.currentReconciliation = null;
        }
    })
    .catch(error => {
        alert('Failed to apply changes. Please try again.');
        applyButton.disabled = false;
        applyButton.textContent = 'Apply All Changes';
    });
}

function reviewMemberChanges() {
    if (!window.currentReconciliation) {
        alert('No reconciliation data available. Please upload a PDF first.');
        return;
    }
    
    // Create and show detailed review modal
    createReviewModal();
}

function createReviewModal() {
    // Initialize approval states if not exists
    if (!window.memberChangeApprovals) {
        window.memberChangeApprovals = {
            new_members: {},
            updates: {},
            removed: {}
        };
        
        // Default all to approved
        window.currentReconciliation.new_members.forEach((member, index) => {
            window.memberChangeApprovals.new_members[index] = true;
        });
        
        window.currentReconciliation.updates.forEach((update, index) => {
            window.memberChangeApprovals.updates[index] = true;
        });
        
        window.currentReconciliation.removed.forEach((member, index) => {
            window.memberChangeApprovals.removed[index] = true;
        });
    }
    
    // Create modal HTML
    const modalHTML = `
        <div id="review-changes-modal" class="modal" style="display: block;">
            <div class="modal-content">
                <button class="close-btn" onclick="closeReviewModal()">✕ Close</button>
                <h2>Review Individual Changes</h2>
                <p>Select which changes you want to apply to the database:</p>
                
                <div class="review-summary">
                    <div class="summary-actions">
                        <button class="action-btn edit-btn" onclick="approveAll()">✅ Approve All</button>
                        <button class="action-btn cancel-btn" onclick="rejectAll()">❌ Reject All</button>
                        <button class="action-btn save-btn" onclick="applySelectedChanges()">Apply Selected Changes</button>
                    </div>
                </div>
                
                <div id="review-content">
                    ${generateReviewContent()}
                </div>
            </div>
        </div>
    `;
    
    // Remove existing review modal if present
    const existingModal = document.getElementById('review-changes-modal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    // Initialize the button counter
    updateReviewSummary();
}

function generateReviewContent() {
    let html = '';
    
    // New Members Section
    if (window.currentReconciliation.new_members.length > 0) {
        html += `
            <div class="review-section">
                <h3>New Members (${window.currentReconciliation.new_members.length})</h3>
                <div class="review-items">
        `;
        
        window.currentReconciliation.new_members.forEach((member, index) => {
            const isApproved = window.memberChangeApprovals.new_members[index];
            html += `
                <div class="review-item ${isApproved ? 'approved' : 'rejected'}">
                    <div class="review-checkbox">
                        <input type="checkbox" id="new_${index}" ${isApproved ? 'checked' : ''} 
                               onchange="toggleApproval('new_members', ${index}, this.checked); setTimeout(updateReviewSummary, 10)">
                        <label for="new_${index}"></label>
                    </div>
                    <div class="review-details">
                        <strong>${member.last_name}, ${member.first_name}</strong>
                        <span class="review-date">DOB: ${member.birthdate}</span>
                    </div>
                </div>
            `;
        });
        
        html += `
                </div>
            </div>
        `;
    }
    
    // Updates Section
    if (window.currentReconciliation.updates.length > 0) {
        html += `
            <div class="review-section">
                <h3>Updates (${window.currentReconciliation.updates.length})</h3>
                <div class="review-items">
        `;
        
        window.currentReconciliation.updates.forEach((update, index) => {
            const isApproved = window.memberChangeApprovals.updates[index];
            const current = update.current;
            const changes = update.changes;
            
            html += `
                <div class="review-item ${isApproved ? 'approved' : 'rejected'}">
                    <div class="review-checkbox">
                        <input type="checkbox" id="update_${index}" ${isApproved ? 'checked' : ''} 
                               onchange="toggleApproval('updates', ${index}, this.checked); setTimeout(updateReviewSummary, 10)">
                        <label for="update_${index}"></label>
                    </div>
                    <div class="review-details">
                        <strong>${current.last_name}, ${current.first_name}</strong>
                        <span class="review-confidence">Confidence: ${Math.round(update.confidence * 100)}%</span>
                        <div class="review-changes">
            `;
            
            Object.keys(changes).forEach(field => {
                const change = changes[field];
                let fieldName = field.replace('_', ' ');
                fieldName = fieldName.charAt(0).toUpperCase() + fieldName.slice(1);
                html += `<div class="change-detail">${fieldName}: <span class="old-value">${change.old}</span> → <span class="new-value">${change.new}</span></div>`;
            });
            
            html += `
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += `
                </div>
            </div>
        `;
    }
    
    // Removed Members Section
    if (window.currentReconciliation.removed.length > 0) {
        html += `
            <div class="review-section">
                <h3>❌ Mark as Moved Away (${window.currentReconciliation.removed.length})</h3>
                <div class="review-items">
        `;
        
        window.currentReconciliation.removed.forEach((member, index) => {
            const isApproved = window.memberChangeApprovals.removed[index];
            html += `
                <div class="review-item ${isApproved ? 'approved' : 'rejected'}">
                    <div class="review-checkbox">
                        <input type="checkbox" id="removed_${index}" ${isApproved ? 'checked' : ''} 
                               onchange="toggleApproval('removed', ${index}, this.checked); setTimeout(updateReviewSummary, 10)">
                        <label for="removed_${index}"></label>
                    </div>
                    <div class="review-details">
                        <strong>${member.last_name}, ${member.first_name}</strong>
                        <span class="review-date">DOB: ${member.birthdate}</span>
                        <div class="review-note">Not found in PDF - will be marked as "moved away"</div>
                    </div>
                </div>
            `;
        });
        
        html += `
                </div>
            </div>
        `;
    }
    
    return html;
}

function toggleApproval(category, index, approved) {
    window.memberChangeApprovals[category][index] = approved;
    
    // Update visual state
    let elementId;
    if (category === 'new_members') {
        elementId = `new_${index}`;
    } else if (category === 'updates') {
        elementId = `update_${index}`;
    } else if (category === 'removed') {
        elementId = `removed_${index}`;
    }
    
    const element = document.getElementById(elementId);
    if (element) {
        const item = element.closest('.review-item');
        if (item) {
            if (approved) {
                item.classList.add('approved');
                item.classList.remove('rejected');
            } else {
                item.classList.add('rejected');
                item.classList.remove('approved');
            }
        }
    }
}

function approveAll() {
    // Check all checkboxes and trigger their change events
    document.querySelectorAll('#review-changes-modal input[type="checkbox"]').forEach(checkbox => {
        checkbox.checked = true;
        // Manually trigger the onchange event
        checkbox.dispatchEvent(new Event('change'));
    });
}

function rejectAll() {
    // Uncheck all checkboxes and trigger their change events
    document.querySelectorAll('#review-changes-modal input[type="checkbox"]').forEach(checkbox => {
        checkbox.checked = false;
        // Manually trigger the onchange event
        checkbox.dispatchEvent(new Event('change'));
    });
}

function updateReviewSummary() {
    // Count checked checkboxes directly from the DOM
    const checkedBoxes = document.querySelectorAll('#review-changes-modal input[type="checkbox"]:checked');
    const total = checkedBoxes.length;
    
    // Update apply button text
    const applyBtn = document.querySelector('#review-changes-modal .save-btn');
    if (applyBtn) {
        applyBtn.textContent = `Apply Selected Changes (${total})`;
    }
}

function applySelectedChanges() {
    // Filter reconciliation data to only include approved changes
    const filteredReconciliation = {
        new_members: window.currentReconciliation.new_members.filter((_, index) => 
            window.memberChangeApprovals.new_members[index]
        ),
        updates: window.currentReconciliation.updates.filter((_, index) => 
            window.memberChangeApprovals.updates[index]
        ),
        removed: window.currentReconciliation.removed.filter((_, index) => 
            window.memberChangeApprovals.removed[index]
        ),
        no_changes: window.currentReconciliation.no_changes
    };
    
    const totalChanges = filteredReconciliation.new_members.length + 
                        filteredReconciliation.updates.length + 
                        filteredReconciliation.removed.length;
    
    if (totalChanges === 0) {
        alert('No changes selected to apply.');
        return;
    }
    
    if (!confirm(`Apply ${totalChanges} selected changes to the database?`)) {
        return;
    }
    
    // Close review modal
    closeReviewModal();
    
    // Apply the filtered changes
    applyFilteredChanges(filteredReconciliation);
}

function closeReviewModal() {
    const modal = document.getElementById('review-changes-modal');
    if (modal) {
        modal.remove();
    }
}

async function applyFilteredChanges(filteredReconciliation) {
    try {
        const response = await fetch('apply_member_changes.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                reconciliation: filteredReconciliation
            })
        });
        
        const data = await response.json();
        
        if (data.error) {
            alert('Error applying changes: ' + data.message);
        } else {
            alert(`Selected changes applied successfully!\n\nNew members added: ${data.summary.new_members_added}\nMembers updated: ${data.summary.members_updated}\nMembers marked as removed: ${data.summary.members_removed}`);
            closeUpdateMembersModal();
            
            // Refresh member data if we're on the Member Information tab
            if (document.getElementById('Tab3').style.display !== 'none') {
                loadMembersForm();
            }
            
            // Clear stored data
            window.currentReconciliation = null;
            window.memberChangeApprovals = null;
        }
    } catch (error) {
        alert('Failed to apply changes. Please try again.');
    }
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

// ============ CALLING HISTORY MODAL FUNCTIONS ============

// Global variable to store current member info and history entries
let currentMemberId = null;
let callingHistoryEntries = [];

// Function to open the calling history modal
function openAddCallingHistoryModal() {
    const memberSelect = document.getElementById('member-form-select');
    const memberId = memberSelect.value;
    const memberName = memberSelect.options[memberSelect.selectedIndex].text;
    
    if (!memberId) {
        alert('Please select a member first.');
        return;
    }
    
    // Store current member info
    currentMemberId = memberId;
    
    // Set member name in modal
    document.getElementById('history-member-name').textContent = memberName;
    
    // Clear form fields
    document.getElementById('history-calling-select').value = '';
    document.getElementById('history-period').value = '';
    document.getElementById('history-notes').value = '';
    
    // Clear table and entries
    callingHistoryEntries = [];
    updateHistoryTable();
    
    // Populate calling dropdown
    populateHistoryCallingDropdown();
    
    // Show modal
    document.getElementById('add-calling-history-modal').style.display = 'block';
    document.getElementById('popup-overlay').style.display = 'block';
}

// Function to close the calling history modal
function closeAddCallingHistoryModal() {
    document.getElementById('add-calling-history-modal').style.display = 'none';
    document.getElementById('popup-overlay').style.display = 'none';
    
    // Clear form and reset
    document.getElementById('history-calling-select').value = '';
    document.getElementById('history-period').value = '';
    document.getElementById('history-notes').value = '';
    document.getElementById('history-form-message').textContent = '';
    callingHistoryEntries = [];
    currentMemberId = null;
}

// Function to populate the calling dropdown in the modal
function populateHistoryCallingDropdown() {
    fetch('get_callings.php')
        .then(response => response.json())
        .then(data => {
            const select = document.getElementById('history-calling-select');
            select.innerHTML = '<option value="">Select a Calling</option>';
            
            data.forEach(calling => {
                const option = document.createElement('option');
                option.value = calling.calling_id;
                option.textContent = calling.calling_name;
                select.appendChild(option);
            });
        })
        .catch(error => {
            console.error('Error loading callings:', error);
            document.getElementById('history-form-message').textContent = 'Error loading callings list.';
        });
}

// Function to add a calling to the history table
function addCallingToHistoryTable() {
    const callingSelect = document.getElementById('history-calling-select');
    const periodInput = document.getElementById('history-period');
    const notesInput = document.getElementById('history-notes');
    
    const callingId = callingSelect.value;
    const callingName = callingSelect.options[callingSelect.selectedIndex].text;
    const period = periodInput.value.trim();
    const notes = notesInput.value.trim();
    
    if (!callingId) {
        alert('Please select a calling.');
        return;
    }
    
    // Check if calling is already in the list
    const existingEntry = callingHistoryEntries.find(entry => entry.callingId === callingId);
    if (existingEntry) {
        alert('This calling is already in the list.');
        return;
    }
    
    // Add to entries array
    callingHistoryEntries.push({
        callingId: callingId,
        callingName: callingName,
        period: period,
        notes: notes
    });
    
    // Clear form fields
    callingSelect.value = '';
    periodInput.value = '';
    notesInput.value = '';
    
    // Update table display
    updateHistoryTable();
    
    document.getElementById('history-form-message').textContent = '';
}

// Function to update the history table display
function updateHistoryTable() {
    const table = document.getElementById('history-table');
    const tbody = document.getElementById('history-table-body');
    const noMessage = document.getElementById('no-history-message');
    const saveBtn = document.getElementById('save-history-btn');
    
    if (callingHistoryEntries.length === 0) {
        table.style.display = 'none';
        noMessage.style.display = 'block';
        saveBtn.style.display = 'none';
    } else {
        table.style.display = 'table';
        noMessage.style.display = 'none';
        saveBtn.style.display = 'inline-block';
        
        // Clear tbody
        tbody.innerHTML = '';
        
        // Add rows
        callingHistoryEntries.forEach((entry, index) => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${entry.callingName}</td>
                <td>${entry.period || '—'}</td>
                <td>${entry.notes || '—'}</td>
                <td><button type="button" class="action-btn remove-btn" onclick="removeHistoryEntry(${index})">Remove</button></td>
            `;
            tbody.appendChild(row);
        });
    }
}

// Function to remove an entry from the history table
function removeHistoryEntry(index) {
    callingHistoryEntries.splice(index, 1);
    updateHistoryTable();
}

// Function to save all calling history entries
function saveCallingHistory() {
    if (!currentMemberId || callingHistoryEntries.length === 0) {
        alert('No calling history to save.');
        return;
    }
    
    const messageDiv = document.getElementById('history-form-message');
    messageDiv.textContent = 'Saving calling history...';
    
    const data = {
        member_id: currentMemberId,
        history_entries: callingHistoryEntries
    };
    
    fetch('save_calling_history.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            messageDiv.style.color = 'green';
            messageDiv.textContent = `Successfully saved ${callingHistoryEntries.length} calling history entries.`;
            
            // Clear entries and update table
            callingHistoryEntries = [];
            updateHistoryTable();
            
            // Auto-close modal after success
            setTimeout(() => {
                closeAddCallingHistoryModal();
            }, 2000);
            
        } else {
            messageDiv.style.color = 'red';
            messageDiv.textContent = 'Error: ' + (result.message || 'Failed to save calling history.');
        }
    })
    .catch(error => {
        console.error('Error saving calling history:', error);
        messageDiv.style.color = 'red';
        messageDiv.textContent = 'Error saving calling history. Please try again.';
    });
}

// Function to handle considering checkbox changes
function toggleCallingConsidering(checkbox, event) {
    // Stop the event from bubbling up to prevent modal from opening
    if (event) {
        event.stopPropagation();
        event.preventDefault();
    }

    const callingId = checkbox.getAttribute('data-calling-id');
    const considering = checkbox.checked;

    // Find the calling name span to update styling
    const callingNameSpan = checkbox.parentElement.querySelector('.calling-name');

    // Update the UI immediately for responsiveness
    if (considering) {
        callingNameSpan.classList.add('considering');
    } else {
        callingNameSpan.classList.remove('considering');
    }

    // Send update to backend
    fetch('update_calling_considering.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            calling_id: parseInt(callingId),
            considering: considering
        })
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            // Revert the UI change if the backend failed
            checkbox.checked = !considering;
            if (considering) {
                callingNameSpan.classList.remove('considering');
            } else {
                callingNameSpan.classList.add('considering');
            }
            console.error('Failed to update considering status:', data.error);
            alert('Failed to update considering status. Please try again.');
        }
    })
    .catch(error => {
        // Revert the UI change if the request failed
        checkbox.checked = !considering;
        if (considering) {
            callingNameSpan.classList.remove('considering');
        } else {
            callingNameSpan.classList.add('considering');
        }
        console.error('Error updating considering status:', error);
        alert('Error updating considering status. Please try again.');
    });
}


