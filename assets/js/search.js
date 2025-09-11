document.addEventListener('DOMContentLoaded', function() {

    // Search functionality redirect on all non-finder pages
    document.getElementById('vis-search').addEventListener('submit', function(e) {

        // Prevent the form from submitting the default way
        e.preventDefault(); 

        // Get the search term
        const searchTerm = document.querySelector('input[name="search"]').value;

        if (searchTerm) {
            
            // URL-encode the search term
            const encodedSearchTerm = encodeURIComponent(searchTerm).toLowerCase(); 
            
            // Construct the new URL with the hash
            const newUrl = '/search-results/#textsearch=' + encodedSearchTerm; 

            // Redirect to the new URL
            window.location.href = newUrl;

        }

        // Amend history entry with the #textsearch string
        history.replaceState({}, "", `#textsearch=${encodedSearchTerm}`);

    });

});