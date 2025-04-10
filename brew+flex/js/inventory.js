function openUpdateModal(id, name, type, quantity, price) {
    document.getElementById('item_id').value = id;
    document.getElementById('item_name').value = name;
    document.getElementById('item_type').value = type;
    document.getElementById('item_quantity').value = quantity;
    document.getElementById('item_price').value = price;

    document.getElementById('updateModal').style.display = "flex";
}

function closeUpdateModal() {
    document.getElementById('updateModal').style.display = "none";
}

function openAddItemModal() {
document.getElementById("addItemModal").style.display = "flex";
}

function closeAddItemModal() {
document.getElementById("addItemModal").style.display = "none";
}
window.onclick = function(event) {
var addItemModal = document.getElementById("addItemModal");
if (event.target === addItemModal) {
addItemModal.style.display = "none";
}
};


document.addEventListener('DOMContentLoaded', function() {
const categorySelect = document.getElementById('type');
const dateAcquiredGroup = document.getElementById('date-acquired-group');
const dateAcquiredField = document.getElementById('date_acquired');

// Function to toggle the visibility of the Date Acquired field
function toggleDateAcquiredField() {
if (categorySelect.value === 'Gym Equipment') {
    dateAcquiredGroup.style.display = 'block'; // Show the Date Acquired field
    dateAcquiredField.setAttribute('required', 'true'); // Make it required
} else {
    dateAcquiredGroup.style.display = 'none'; // Hide the Date Acquired field
    dateAcquiredField.removeAttribute('required'); // Remove the required attribute
}
}

// Initial check on page load
toggleDateAcquiredField();

// Add event listener for changes to the dropdown
categorySelect.addEventListener('change', toggleDateAcquiredField);


});// Get modal, button, and form elements
var sortModal = document.getElementById('sortModal');
var sortButton = document.getElementById('sortButton');
var sortForm = document.getElementById('sortForm');  // The form for sorting

// Open the modal when the "Sort" button is clicked
sortButton.onclick = function() {
sortModal.style.display = "flex"; // Show modal
}

// Close the modal if user clicks outside the modal content
window.onclick = function(event) {
const updateModal = document.getElementById("updateModal");
const addItemModal = document.getElementById("addItemModal");
const confirmationModal = document.getElementById("confirmationModal");
const sortModal = document.getElementById('sortModal');

// Close the Update Modal if clicked outside of it
if (event.target === updateModal) {
updateModal.style.display = "none"; // Hide the Update Modal
}

// Close the Add Item Modal if clicked outside of it
if (event.target === addItemModal) {
addItemModal.style.display = "none"; // Hide the Add Item Modal
}

// Close the Confirmation Modal if clicked outside of it
if (event.target === confirmationModal) {
confirmationModal.style.display = "none"; // Hide the Confirmation Modal
}
if (event.target === sortModal) {
sortModal.style.display = "none"; // Hide the Sort Modal
}

};

// Close the modal and apply sorting when the form is submitted
sortForm.onsubmit = function(event) {
// Optionally, you can do any form validation here
// Then close the modal
sortModal.style.display = "none"; // Hide modal after sorting
}
// Function to filter the inventory table based on the search input
function searchItems() {
const input = document.getElementById('searchInput');
const filter = input.value.toLowerCase();
const rows = document.querySelectorAll('#inventoryTableBody tr');

rows.forEach(row => {
const itemName = row.cells[1].textContent.toLowerCase(); // Item Name is in the 2nd column (index 1)
if (itemName.includes(filter)) {
    row.style.display = ""; // Show row if it matches the search filter
} else {
    row.style.display = "none"; // Hide row if it doesn't match
}
});
}

// Show confirmation modal for updating the item
function confirmUpdate(event) {
event.preventDefault();  // Prevent form submission
const modal = document.getElementById("confirmationModal");
modal.style.display = "flex"; // Show the modal
const confirmYesBtn = document.getElementById("confirmYesBtn");

// Confirm the update action
confirmYesBtn.onclick = function() {
document.getElementById("updateItemForm").submit(); // Submit the form
closeConfirmationModal(); // Close the confirmation modal
};
}

// Show confirmation modal for adding the item
function confirmAddItem(event) {
event.preventDefault();  // Prevent form submission
const modal = document.getElementById("confirmationModal");
modal.style.display = "flex"; // Show the modal
const confirmYesBtn = document.getElementById("confirmYesBtn");

// Confirm the add item action
confirmYesBtn.onclick = function() {
document.getElementById("addItemForm").submit(); // Submit the form
closeConfirmationModal(); // Close the confirmation modal
};
}

// Close confirmation modal
function closeConfirmationModal() {
const modal = document.getElementById("confirmationModal");
modal.style.display = "none"; // Hide the modal
}

// Close the update modal
function closeUpdateModal() {
document.getElementById("updateModal").style.display = "none";
}

// Close the add item modal
function closeAddItemModal() {
document.getElementById("addItemModal").style.display = "none";
}