document.addEventListener('DOMContentLoaded', () => {
    // --- ELEMENT SELECTION ---
    const form = document.getElementById('logTestForm');
    const steps = document.querySelectorAll('.form-step');
    const stepIndicators = document.querySelectorAll('.step');
    const productSearchInput = document.getElementById('productSearch');
    const productResultsDiv = document.getElementById('productSearchResults');
    const testTypeSelect = document.getElementById('testType');
    const prevBtn = document.getElementById('prevBtn');
    
    let currentStep = 1;
    let selectedProductId = null;

    // --- MULTI-STEP FORM NAVIGATION ---
    function showStep(step) {
        steps.forEach(s => s.classList.remove('active'));
        stepIndicators.forEach(si => si.classList.remove('active'));
        steps[step - 1].classList.add('active');
        stepIndicators[step - 1].classList.add('active');
        currentStep = step;

        // Show/hide previous button
        prevBtn.style.display = (step === 1) ? 'none' : 'inline-flex';
    }

    // --- PRODUCT SEARCH AUTOCOMPLETE ---
    productSearchInput.addEventListener('input', async () => {
        const query = productSearchInput.value;
        if (query.length < 2) {
            productResultsDiv.innerHTML = '';
            return;
        }

        const response = await fetch(`log_test.php?action=search_products&q=${encodeURIComponent(query)}`);
        const products = await response.json();
        
        productResultsDiv.innerHTML = '';
        if (products.data && products.data.length > 0) {
            products.data.forEach(p => {
                const div = document.createElement('div');
                div.textContent = `${p.product_id} - ${p.product_type}`;
                div.addEventListener('click', () => selectProduct(p));
                productResultsDiv.appendChild(div);
            });
        } else {
            productResultsDiv.innerHTML = '<div>No products found.</div>';
        }
    });

    function selectProduct(product) {
        productSearchInput.value = product.product_id;
        productResultsDiv.innerHTML = '';
        selectedProductId = product.product_id;
        
        // Enable and populate test types
        loadTestTypes();
        showStep(2); // Move to next step
    }

    async function loadTestTypes() {
        testTypeSelect.innerHTML = '<option value="">-- Loading --</option>';
        testTypeSelect.disabled = true;

        const response = await fetch('log_test.php?action=get_test_types');
        const types = await response.json();
        
        testTypeSelect.innerHTML = '<option value="">-- Select a Test --</option>';
        if (types.data) {
            types.data.forEach(type => {
                const option = document.createElement('option');
                option.value = type.id;
                option.textContent = type.test_name;
                testTypeSelect.appendChild(option);
            });
        }
        testTypeSelect.disabled = false;
    }

    // --- FORM SUBMISSION ---
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const formData = new FormData(form);
        formData.append('log_test', 'true');

        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging...';

        try {
            const response = await fetch('log_test.php', { method: 'POST', body: formData });
            const result = await response.json();

            if (result.success) {
                alert(result.message);
                form.reset();
                showStep(1); // Go back to first step
                selectedProductId = null;
                testTypeSelect.disabled = true;
                location.reload(); // Reload to see new test in sidebar
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            console.error('Submission Error:', error);
            alert('An unexpected error occurred.');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    });

    // --- STEP NAVIGATION ---
    form.addEventListener('click', (e) => {
        if (e.target.id === 'prevBtn' && currentStep > 1) {
            showStep(currentStep - 1);
        }
    });

    // Hide autocomplete results when clicking outside
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.autocomplete-wrapper')) {
            productResultsDiv.innerHTML = '';
        }
    });
});