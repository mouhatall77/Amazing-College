const sirscFindAncestor = ( item, className ) => {
	while (
		( item = item.parentElement ) &&
		! item.classList.contains( className )
	);
	return item;
};

const sirscToggleRenameInfo = () => {
	let togglers = document.querySelectorAll( '.sirsc-imgseo-toggler' );
	if ( togglers ) {

		[].forEach.call( togglers, ( toggler ) => {
			toggler.addEventListener( 'click', () => {
				const parent = sirscFindAncestor( toggler, 'file-info' );
				if ( parent ) {
					let toggles = parent.querySelectorAll( '.sirsc_imgseo-toggle' );
					if ( toggles ) {
						[].forEach.call( toggles, ( toggle ) => {
							if ( toggle.classList.contains( 'is-hidden' ) ) {
								toggle.classList.remove( 'is-hidden' );
							} else {
								toggle.classList.add( 'is-hidden' );
							}
						} );
					}
				}
			} );
		} );
	}
}

const sirscIsRenameToggle = () => {
	let item = document.getElementById( '_sirsc_imgseo_settings_override_filename' );
	if ( item ) {
		item.addEventListener( 'click', function() {
			let item2 = document.getElementById( '_sirsc_imgseo_settings_track_initial' );
			if ( item.checked === true ) {
				item2.removeAttribute( 'disabled' );
			} else {
				item2.disabled = true;
				item2.checked = false;
			}
		} );
	}
}

const sirscGetBulkTypes = () => {
	let types = '';
	let opt = document.querySelectorAll( '[name^=_sirsc_imgseo_bulk_update]:checked' );
	if ( opt ) {
		for ( let i = 0; i < opt.length; i ++ ) {
			types += ( types === '' ) ? '' : ',';
			types += opt[ i ].value;
		}
	}
	return types;
}

const sirscIsBulkRename = () => {
	sirscIteratorGet( 'action=sirsc_adon_is_execute_bulk_rename&bulk_types=' + sirscGetBulkTypes(), 'sirsc-listing-wrap' , 'sirscToggleRenameInfo()' );
}

const sirscIsBulkRenameFinish = ( text ) => {
	let elem = document.getElementById( 'sirsc-listing-wrap' );
	if ( elem ) {
		let opt = elem.querySelectorAll( '.sirsc-progress-wrap' );
		if ( opt ) {
			opt[0].innerHTML = '';
			let progr = document.createElement( 'div' );
			progr.className = 'processed color';
			progr.style.width = '100%';
			progr.innerHTML = '100%';
			opt[0].appendChild( progr );
		}

		let opt2 = document.getElementById( 'sirsc-feature-files-renamed' );
		if ( opt2 ) {
			opt2.innerHTML = text;
		}
	}

	sirscIteratorGet( 'action=sirsc_adon_is_execute_bulk_rename&type=finish&bulk_types=' + sirscGetBulkTypes(), 'sirsc-listing-wrap' );
}

sirscIsRenameToggle();
sirscToggleRenameInfo();
