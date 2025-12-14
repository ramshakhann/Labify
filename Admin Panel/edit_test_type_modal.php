<!-- Edit Test Type Modal -->
<div id="editTestTypeModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Edit Test Type</h3>
            <button class="modal-close" onclick="closeModal('editTestTypeModal')">&times;</button>
        </div>
        <form id="editTestTypeForm">
            <div class="form-group">
                <label class="form-label" for="editTestId">Test ID</label>
                <input type="text" id="editTestId" class="form-input" readonly>
            </div>
            <div class="form-group">
                <label class="form-label" for="editTestCode">Test Code</label>
                <input type="text" id="editTestCode" class="form-input" placeholder="e.g., VLT" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="editTestName">Test Name</label>
                <input type="text" id="editTestName" class="form-input" placeholder="e.g., Voltage Withstand Test" required>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editTestTypeModal')">Cancel</button>
                <button type="submit" class="btn">Update Test Type</button>
            </div>
        </form>
    </div>
</div>