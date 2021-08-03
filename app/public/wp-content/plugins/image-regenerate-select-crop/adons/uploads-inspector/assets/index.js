const sirscUiDisplaySummary = () => {
	sirscIteratorGet( 'action=sirsc_adon_ui_display_summary', 'sirsc-summary-wrap' );
};

const sirscUiDisplayFilesInfo = () => {
	sirscIteratorGet( 'action=sirsc_adon_ui_display_filesinfo', 'sirsc-filesinfo-wrap', 'sirscUiShowListing();' );
};

const sirscUiStartRefresh = () => {
	let list = document.getElementById( 'sirsc-listing-wrap' );
	if ( list ) {
		list.innerHTML = '';
	}
	sirscIteratorGet( 'action=sirsc_adon_ui_execute_refresh', 'sirsc-filesinfo-wrap' );
};

const sirscUiStartAssess = () => {
	let list = document.getElementById( 'sirsc-listing-wrap' );
	if ( list ) {
		list.innerHTML = '';
	}
	sirscIteratorGet( 'action=sirsc_adon_ui_execute_assess', 'sirsc-filesinfo-wrap' );
};

const sirscUiStartAssessCron = () => {
	sisrcShowLightboxBulk();
	sirscExecuteGetRequest(
		'action=sirsc_adon_ui_execute_assess',
		'sirsc-lightbox'
	);
};

const sirscHideInspectorAssessButton = () => {
	console.log( 'hide lightbox' );
}

const navigateToPage = ( event ) => {
	event.preventDefault();
	event.stopPropagation();
	let item = event.target;
	let parent = item.dataset.parentaid;
	let wrapper = document.getElementById( 'sirsc-listing-wrap' );
	if ( wrapper ) {
		item.scrollIntoView({behavior: "smooth", block: "start"});
	}

	if ( parent ) {
		item = document.getElementById( parent );
		item.dataset.page = event.target.textContent;
	}
	sirscIteratorGet(
		'action=sirsc_adon_ui_display_listing'
		+ '&aid=' + item.id
		+ '&title=' + item.dataset.title
		+ '&page=' + item.dataset.page
		+ '&maxpage=' + item.dataset.maxpage
		+ '&sizename=' + item.dataset.sizename
		+ '&mimetype=' + item.dataset.mimetype
		+ '&valid=' + item.dataset.valid,
		'sirsc-listing-wrap',
		'sirscUiShowListing();'
	);
	if ( parent ) {
		item.dataset.page = 1;
	}
};

const sirscUiShowListing = () => {
	let lists = document.querySelectorAll( '.sirsc-listing-wrap-item' );
	if ( lists ) {
		[].forEach.call( lists, ( item ) => {
			item.removeEventListener( 'click', navigateToPage );
			item.addEventListener( 'click', navigateToPage );
		} );
	}
};

const sirscUiFinishUp = () => {
	setTimeout(
		function() {
			sirscUiDisplaySummary();
			sirscUiDisplayFilesInfo();
		},
		sirscIteratorSettings.delay
	);
};

sirscUiShowListing();
