const sirscUfiStartRefresh = () => {
	let list = document.getElementById( 'sirsc-listing-wrap' );
	if ( list ) {
		list.innerHTML = '';
	}
	sirscIteratorGet( 'action=sirsc_adon_ufi_execute_refresh', 'sirsc-filesinfo-wrap' );
}

const sirscUfiDisplaySummary = () => {
	sirscIteratorGet( 'action=sirsc_adon_ufi_display_summary', 'sirsc-summary-wrap' );
}
