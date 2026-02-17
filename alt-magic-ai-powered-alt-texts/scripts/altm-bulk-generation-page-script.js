document.addEventListener('DOMContentLoaded', function () {
    // Number formatting function
    function formatNumber(num) {
        if (num === null || num === undefined) return num;
        return parseInt(num).toLocaleString();
    }

    const bulkGenerationData = {
        ajaxurl: altMagicBulkGeneration.ajaxurl,
        nonce: altMagicBulkGeneration.nonce
    };

    const getImageWithoutAltTextsData = {
        ajaxurl: altMagicBulkGeneration.ajaxurl,
        nonce: altMagicBulkGeneration.getImageWithoutAltTextsNonce
    };

    let is_account_active = altMagicBulkGeneration.isAccountActive;
    let imageCount = altMagicBulkGeneration.imageCount;
    let processedCount = 0;
    let isProcessing = false;

    function fetchCredits() {
        document.querySelector('.credits-available-text').textContent = '... credits';
        fetch(bulkGenerationData.ajaxurl, {
            method: 'POST',
            body: new URLSearchParams({
                'action': 'altm_fetch_user_credits',
                'nonce': bulkGenerationData.nonce
            })
        })
            .then(response => response.json()) // Parse the response as JSON
            .then(data => {
                //console.log("Fetch user credits data: ", data);
                if (data.credits_available || data.credits_available == 0) {
                    const creditsTextElement = document.querySelector('.credits-available-text');
                    creditsTextElement.textContent = formatNumber(data.credits_available) + ' credits';

                    if (data.credits_available == 0) {
                        disableGenerateButton();
                        document.getElementById('account-info-text').style.backgroundColor = '#ffc4c0';
                        document.getElementById('account-info-text').style.color = '#a73931';
                        creditsTextElement.style.color = '#a73931';
                    } else {
                        const generateButton = document.getElementById('generate-bulk-alt-texts');
                        generateButton.disabled = false;
                        generateButton.style.cursor = 'pointer';
                        generateButton.style.backgroundColor = '';
                        generateButton.style.color = '';
                        document.getElementById('account-info-text').style.backgroundColor = '';
                    }

                    if (imageCount == 0) {
                        disableGenerateButton();
                    }
                } else {
                    console.log(data);
                }

            })
            .catch(error => console.error('Error fetching credits:', error));
    }

    function disableGenerateButton() {
        const generateButton = document.getElementById('generate-bulk-alt-texts');
        generateButton.disabled = true;
        generateButton.style.cursor = 'not-allowed';
        generateButton.style.backgroundColor = '#e0e0e0';
        generateButton.style.color = '#a0a0a0';
    }

    async function fetchImageStats() {
        try {
            const response = await fetch(bulkGenerationData.ajaxurl, {
                method: 'POST',
                body: new URLSearchParams({
                    'action': 'altm_get_image_stats'
                })
            });
            const data = await response.json();
            if (data) {
                imageCount = data.images_with_missing_alt;
                document.querySelector('.card-total-images h3').innerText = formatNumber(data.total_images);
                document.querySelector('.card-images-with-missing-alt h3').innerText = formatNumber(data.images_with_missing_alt);
            }
        } catch (error) {
            console.error('Error fetching image stats:', error);
        }
    }

    document.getElementById('generate-bulk-alt-texts').addEventListener('click', handleBulkImageAltGeneration);
    document.getElementById('stop-bulk-alt-texts').addEventListener('click', function () {
        if (confirm('Are you sure you want to stop the further bulk alt text generation process?')) {
            location.reload();
        }
    });

    async function handleBulkImageAltGeneration() {
        isProcessing = true;
        document.getElementById('generate-bulk-alt-texts').style.display = 'none';
        document.getElementById('stop-bulk-alt-texts').style.display = 'block';
        document.getElementById('spinner').style.display = 'block';
        document.getElementById('progress-bar-container').style.display = 'block';
        document.getElementById('processed-count').style.display = 'block';
        document.getElementById('close-warning-note').style.display = 'block';

        const imageIds = await getImageIds();
        const batchSize = 1;
        let index = 0;

        async function processBatch() {
            if (!isProcessing) return;
            if (index >= imageIds.length) {
                stopBulkImageAltGeneration();
                return;
            }

            const batch = imageIds.slice(index, index + batchSize);
            index += batchSize;

            //console.log('Processing batch: ', batch);

            try {
                const response = await fetch(bulkGenerationData.ajaxurl, {
                    method: 'POST',
                    body: new URLSearchParams({
                        'action': 'altm_handle_bulk_image_alt_generation',
                        'image_ids': JSON.stringify(batch),
                        'nonce': bulkGenerationData.nonce
                    })
                });
                const data = await response.json();
                if (data) {
                    if (data.some(image => image.error === 'no_credits')) {
                        stopBulkImageAltGeneration();
                        return;
                    }
                    updateProcessedCount(data);
                }
            } catch (error) {
                console.error('Error:', error);
                stopBulkImageAltGeneration();
                return;
            }

            await processBatch();
        }

        await processBatch();
    }

    function stopBulkImageAltGeneration() {
        isProcessing = false;
        document.getElementById('generate-bulk-alt-texts').style.display = 'block';
        document.getElementById('stop-bulk-alt-texts').style.display = 'none';
        document.getElementById('spinner').style.display = 'none';
        document.getElementById('close-warning-note').style.display = 'none';

        fetchCredits();
        fetchImageStats();
    }

    async function getImageIds() {
        return await altm_get_image_without_alt_texts_handler();
    }

    async function altm_get_image_without_alt_texts_handler() {
        try {
            const response = await fetch(getImageWithoutAltTextsData.ajaxurl, {
                method: 'POST',
                body: new URLSearchParams({
                    'action': 'altm_get_image_without_alt_texts',
                    'nonce': getImageWithoutAltTextsData.nonce
                })
            });
            const data = await response.json();
            //console.log('altm_get_image_without_alt_texts_handler data: ', data);
            return data;
        } catch (error) {
            console.error('Error fetching images without alt texts:', error);
            return [];
        }
    }

    function updateProcessedCount(data) {
        processedCount += data.length;
        document.getElementById('processed-count').innerHTML = 'Processed: ' + formatNumber(processedCount) + '/' + formatNumber(imageCount) + ' images';

        const progressPercentage = (processedCount / imageCount) * 100;
        document.getElementById('progress-bar').style.width = progressPercentage + '%';
        document.getElementById('progress-percentage').innerText = Math.round(progressPercentage) + '%';
    }

    if (is_account_active) {
        if (imageCount == 0) {
            disableGenerateButton();
        }
    } else {
        disableGenerateButton();
    }

    fetchCredits();


});
