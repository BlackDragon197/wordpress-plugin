jQuery(document).ready(function($) {
    $('#custom-table-search').on('submit', function(e) {
        e.preventDefault();
        var searchQuery = $('input[name="search"]').val();
        $.ajax({
            url: custom_table_ajax.ajax_url,
            method: 'GET',
            data: {
                action: 'search_data',
                search: searchQuery,
                nonce: custom_table_ajax.nonce
            },
            success: function(response) {
                console.log(response); // Debugging
                var resultsTable = $('#custom-table-results');
                resultsTable.find('tr:gt(0)').remove(); // Clear existing results
                if (response.success && response.data.length > 0) {
                    $.each(response.data, function(index, item) {
                        resultsTable.append('<tr><td>' + item.name + '</td><td>' + item.email + '</td><td>' + item.message + '</td></tr>');
                    });
                } else {
                    resultsTable.append('<tr><td colspan="3">No results found</td></tr>');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error); // Debugging
            }
        });
    });
});
