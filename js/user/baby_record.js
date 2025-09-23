$(document).ready(function() {
    // Mobile menu toggle
    document.querySelector('.menu-toggle').addEventListener('click', function() {
        document.getElementById('nav-menu').classList.toggle('active');
        this.classList.toggle('active');
    });

    // Initialize Bootstrap tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Handle edit baby button click
    $(document).on('click', '.edit-baby-btn', function() {
        var babyId = $(this).data('baby-id');
        loadBabyDataForEdit(babyId);
    });

    // Handle edit form submission
    $('#editBabyForm').on('submit', function(e) {
        e.preventDefault();
        saveBabyChanges();
    });

    // Vaccine management in add baby form
    let vaccineCounter = 0;
    $('#addVaccineBtn').click(function() {
        addVaccineField();
    });

    // Initial vaccine field
    addVaccineField();
});

function loadBabyDataForEdit(babyId) {
    $.ajax({
        url: '../../system/user/get_baby_details.php',
        type: 'GET',
        data: { baby_id: babyId, action: 'edit' },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#edit_baby_id').val(response.data.baby_id);
                $('#edit_parent_name').val(response.data.parent_name);
                $('#edit_baby_name').val(response.data.baby_name);
                $('#edit_birthdate').val(response.data.birthdate);
                $('#edit_place_of_birth').val(response.data.place_of_birth);
                $('#edit_birth_height').val(response.data.birth_height);
                $('#edit_birth_weight').val(response.data.birth_weight);
                $('#edit_contact_no').val(response.data.contact_no);
                
                // Set gender radio button
                if (response.data.gender === 'Female') {
                    $('#edit_genderFemale').prop('checked', true);
                } else {
                    $('#edit_genderMale').prop('checked', true);
                }

                // Show the modal
                var editModal = new bootstrap.Modal(document.getElementById('editBabyModal'));
                editModal.show();
            } else {
                alert('Failed to load baby data: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            alert('Error loading baby data: ' + error);
        }
    });
}

function saveBabyChanges() {
    const formData = $('#editBabyForm').serialize();
    
    $.ajax({
        url: '../../system/user/update_baby.php',
        type: 'POST',
        data: formData,
        success: function(response) {
            if (response.success) {
                alert('Baby record updated successfully!');
                location.reload(); // Refresh the page to see changes
            } else {
                alert('Failed to update: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            alert('Error updating baby: ' + error);
        }
    });
}

function addVaccineField() {
    vaccineCounter++;
    const vaccineHtml = `
        <div class="vaccine-row mb-3" data-vaccine-id="${vaccineCounter}">
            <div class="row">
                <div class="col-md-6">
                    <label class="form-label">Vaccine</label>
                    <select name="vaccines[]" class="form-select vaccine-select">
                        <option value="">Select Vaccine</option>
                        <?php foreach ($all_vaccines as $vaccine): ?>
                            <option value="<?= $vaccine['schedule_id'] ?>"><?= htmlspecialchars($vaccine['type_of_vaccine']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Date Administered</label>
                    <input type="date" name="vaccine_dates[]" class="form-control vaccine-date" max="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="button" class="btn btn-sm btn-danger remove-vaccine-btn" style="margin-bottom: 16px;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    
    $('#vaccineContainer').append(vaccineHtml);
    
    // Add event listener for remove button
    $(`.remove-vaccine-btn[data-vaccine-id="${vaccineCounter}"]`).click(function() {
        $(this).closest('.vaccine-row').remove();
    });
}