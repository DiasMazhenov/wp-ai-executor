( function () {
    'use strict';

    const config = window.WPAEBlockLibrary;
    if ( ! config || ! config.endpoint ) {
        return;
    }

    const state = {
        items: [],
        query: '',
        category: '',
        mode: 'preserve',
        selectedId: 0,
        loading: false,
    };
    let root = null;
    let previousFocus = null;

    const createElement = ( tag, className, text ) => {
        const element = document.createElement( tag );
        if ( className ) {
            element.className = className;
        }
        if ( text !== undefined ) {
            element.textContent = text;
        }
        return element;
    };

    const request = async ( url ) => {
        const response = await window.fetch( url, {
            credentials: 'same-origin',
            headers: {
                'X-WP-Nonce': config.nonce,
            },
        } );
        const text = await response.text();
        let payload = {};
        try {
            payload = text ? JSON.parse( text ) : {};
        } catch ( error ) {
            throw new Error( text || config.strings.failed );
        }
        if ( ! response.ok || ! payload.ok ) {
            throw new Error( payload.error || payload.message || config.strings.failed );
        }
        return payload;
    };

    const notify = ( message, type = 'success' ) => {
        if ( window.elementor && elementor.notifications && elementor.notifications.showToast ) {
            elementor.notifications.showToast( { message, type } );
            return;
        }
        if ( type === 'error' ) {
            window.alert( message );
        }
    };

    const getFilteredItems = () => {
        const query = state.query.trim().toLocaleLowerCase();
        return state.items.filter( ( item ) => {
            if ( state.category && item.category !== state.category ) {
                return false;
            }
            if ( ! query ) {
                return true;
            }
            const haystack = [
                item.title,
                item.description,
                item.category,
                ...( item.tags || [] ),
            ].join( ' ' ).toLocaleLowerCase();
            return haystack.includes( query );
        } );
    };

    const getSelectedItem = () => state.items.find(
        ( item ) => Number( item.id ) === Number( state.selectedId )
    ) || null;

    const renderCategories = () => {
        const select = root.querySelector( '[data-wpae-category]' );
        const current = state.category;
        const categories = [ ...new Set( state.items.map( ( item ) => item.category ).filter( Boolean ) ) ].sort();
        select.replaceChildren();
        select.appendChild( new Option( config.strings.allCategories, '' ) );
        categories.forEach( ( category ) => select.appendChild( new Option( category, category ) ) );
        select.value = current;
    };

    const renderDetails = () => {
        const panel = root.querySelector( '[data-wpae-details]' );
        panel.replaceChildren();
        const item = getSelectedItem();
        if ( ! item ) {
            panel.appendChild( createElement( 'p', 'wpae-library__details-empty', config.strings.selectBlock ) );
            return;
        }

        const stats = item.compatibility && item.compatibility.stats ? item.compatibility.stats : {};
        const heading = createElement( 'div', 'wpae-library__details-heading' );
        heading.appendChild( createElement( 'span', 'wpae-library__eyebrow', config.strings.blockDetails ) );
        heading.appendChild( createElement( 'h3', '', item.title ) );
        panel.appendChild( heading );

        if ( item.description ) {
            panel.appendChild( createElement( 'p', 'wpae-library__description', item.description ) );
        }

        const facts = createElement( 'dl', 'wpae-library__facts' );
        [
            [ config.strings.category, item.category || 'custom' ],
            [ config.strings.elements, String( stats.elements || 0 ) ],
            [ config.strings.containers, String( stats.containers || 0 ) ],
            [ config.strings.widgets, String( stats.widgets || 0 ) ],
            [ config.strings.elementorVersion, item.elementor_version || '—' ],
        ].forEach( ( [ label, value ] ) => {
            facts.appendChild( createElement( 'dt', '', label ) );
            facts.appendChild( createElement( 'dd', '', value ) );
        } );
        panel.appendChild( facts );

        const compatibility = createElement( 'div', 'wpae-library__compatibility' );
        compatibility.appendChild( createElement(
            'span',
            item.compatibility && item.compatibility.raw_valid
                ? 'wpae-library__status wpae-library__status--ok'
                : 'wpae-library__status wpae-library__status--warn',
            item.compatibility && item.compatibility.raw_valid
                ? config.strings.valid
                : config.strings.needsNormalization
        ) );
        if ( item.compatibility && item.compatibility.foreign_design_system ) {
            compatibility.appendChild( createElement(
                'span',
                'wpae-library__status wpae-library__status--neutral',
                config.strings.foreignDesign
            ) );
        }
        panel.appendChild( compatibility );

        if ( item.tags && item.tags.length ) {
            const tags = createElement( 'div', 'wpae-library__tags' );
            item.tags.forEach( ( tag ) => tags.appendChild( createElement( 'span', '', tag ) ) );
            panel.appendChild( tags );
        }

        const insert = createElement( 'button', 'wpae-library__insert', config.strings.insert );
        insert.type = 'button';
        insert.dataset.wpaeInsert = String( item.id );
        panel.appendChild( insert );
    };

    const renderItems = () => {
        const list = root.querySelector( '[data-wpae-list]' );
        const count = root.querySelector( '[data-wpae-count]' );
        const items = getFilteredItems();
        count.textContent = String( items.length );
        list.replaceChildren();

        if ( state.loading ) {
            list.appendChild( createElement( 'p', 'wpae-library__empty', config.strings.loading ) );
            return;
        }
        if ( ! items.length ) {
            list.appendChild( createElement( 'p', 'wpae-library__empty', config.strings.noBlocks ) );
            return;
        }

        items.forEach( ( item ) => {
            const stats = item.compatibility && item.compatibility.stats ? item.compatibility.stats : {};
            const card = createElement(
                'button',
                Number( item.id ) === Number( state.selectedId )
                    ? 'wpae-library__item is-selected'
                    : 'wpae-library__item'
            );
            card.type = 'button';
            card.dataset.wpaeBlock = String( item.id );
            card.setAttribute( 'aria-pressed', Number( item.id ) === Number( state.selectedId ) ? 'true' : 'false' );

            const top = createElement( 'span', 'wpae-library__item-top' );
            top.appendChild( createElement( 'span', 'wpae-library__item-category', item.category || 'custom' ) );
            top.appendChild( createElement( 'span', 'wpae-library__item-count', `${ stats.elements || 0 } ${ config.strings.units }` ) );
            card.appendChild( top );
            card.appendChild( createElement( 'strong', 'wpae-library__item-title', item.title ) );
            card.appendChild( createElement(
                'span',
                'wpae-library__item-meta',
                `${ stats.containers || 0 } ${ config.strings.shortContainers } · ${ stats.widgets || 0 } ${ config.strings.shortWidgets }`
            ) );
            list.appendChild( card );
        } );
    };

    const render = () => {
        renderCategories();
        renderItems();
        renderDetails();
    };

    const load = async () => {
        state.loading = true;
        renderItems();
        try {
            const url = new URL( config.endpoint, window.location.origin );
            url.searchParams.set( 'limit', '100' );
            const payload = await request( url.toString() );
            state.items = Array.isArray( payload.items ) ? payload.items : [];
            if ( state.selectedId && ! getSelectedItem() ) {
                state.selectedId = 0;
            }
        } catch ( error ) {
            notify( error.message || config.strings.failed, 'error' );
        } finally {
            state.loading = false;
            render();
        }
    };

    const insertBlock = async ( blockId ) => {
        const button = root.querySelector( '[data-wpae-insert]' );
        if ( button ) {
            button.disabled = true;
            button.textContent = config.strings.inserting;
        }
        try {
            const url = new URL( `${ config.endpoint }/${ blockId }/instantiate`, window.location.origin );
            url.searchParams.set( 'mode', state.mode );
            const payload = await request( url.toString() );
            const storage = {
                type: 'elementor',
                siteurl: elementorCommon.config.urls.rest,
                elements: payload.elementor_data,
            };
            const args = {
                storageType: 'wpae-json',
                data: JSON.stringify( storage ),
            };
            const selected = elementor.selection && elementor.selection.getElements
                ? elementor.selection.getElements()
                : [];
            if ( selected.length ) {
                args.containers = selected;
            }
            const result = await $e.run( 'document/ui/paste', args );
            if ( result === false ) {
                throw new Error( config.strings.insertTargetMissing );
            }
            close();
            notify( config.strings.inserted );
        } catch ( error ) {
            notify( error.message || config.strings.failed, 'error' );
            renderDetails();
        }
    };

    const close = () => {
        if ( root ) {
            root.hidden = true;
            document.body.classList.remove( 'wpae-library-open' );
            if ( previousFocus && typeof previousFocus.focus === 'function' ) {
                previousFocus.focus();
            }
        }
    };

    const handleKeydown = ( event ) => {
        if ( event.key === 'Escape' && root && ! root.hidden ) {
            close();
        }
    };

    const mount = () => {
        if ( root ) {
            return;
        }
        root = createElement( 'div', 'wpae-library' );
        root.hidden = true;
        root.innerHTML = `
            <div class="wpae-library__backdrop" data-wpae-close></div>
            <section class="wpae-library__dialog" role="dialog" aria-modal="true" aria-labelledby="wpae-library-title">
                <header class="wpae-library__header">
                    <div>
                        <span class="wpae-library__eyebrow">${ config.strings.library }</span>
                        <h2 id="wpae-library-title">${ config.strings.libraryTitle }</h2>
                    </div>
                    <button type="button" class="wpae-library__icon-button" data-wpae-close title="${ config.strings.close }" aria-label="${ config.strings.close }">
                        <i class="eicon-close" aria-hidden="true"></i>
                    </button>
                </header>
                <div class="wpae-library__toolbar">
                    <label class="wpae-library__search">
                        <i class="eicon-search" aria-hidden="true"></i>
                        <input type="search" data-wpae-search placeholder="${ config.strings.search }">
                    </label>
                    <select data-wpae-category aria-label="${ config.strings.category }"></select>
                    <div class="wpae-library__modes" role="group" aria-label="${ config.strings.insertMode }">
                        <button type="button" data-wpae-mode="preserve" class="is-active">${ config.strings.preserve }</button>
                        <button type="button" data-wpae-mode="compatibility">${ config.strings.compatibility }</button>
                        <button type="button" data-wpae-mode="adapt">${ config.strings.adapt }</button>
                    </div>
                    <button type="button" class="wpae-library__icon-button" data-wpae-refresh title="${ config.strings.refresh }" aria-label="${ config.strings.refresh }">
                        <i class="eicon-sync" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="wpae-library__body">
                    <div class="wpae-library__catalog">
                        <div class="wpae-library__catalog-head">
                            <span>${ config.strings.blocks }</span>
                            <span data-wpae-count aria-live="polite">0</span>
                        </div>
                        <div class="wpae-library__list" data-wpae-list aria-live="polite"></div>
                    </div>
                    <aside class="wpae-library__details" data-wpae-details></aside>
                </div>
            </section>
        `;
        document.body.appendChild( root );

        root.addEventListener( 'click', ( event ) => {
            const closeTarget = event.target.closest( '[data-wpae-close]' );
            if ( closeTarget ) {
                close();
                return;
            }
            const block = event.target.closest( '[data-wpae-block]' );
            if ( block ) {
                state.selectedId = Number( block.dataset.wpaeBlock );
                renderItems();
                renderDetails();
                return;
            }
            const insert = event.target.closest( '[data-wpae-insert]' );
            if ( insert ) {
                insertBlock( Number( insert.dataset.wpaeInsert ) );
                return;
            }
            const mode = event.target.closest( '[data-wpae-mode]' );
            if ( mode ) {
                state.mode = mode.dataset.wpaeMode;
                root.querySelectorAll( '[data-wpae-mode]' ).forEach(
                    ( item ) => item.classList.toggle( 'is-active', item === mode )
                );
                return;
            }
            if ( event.target.closest( '[data-wpae-refresh]' ) ) {
                load();
            }
        } );
        root.querySelector( '[data-wpae-search]' ).addEventListener( 'input', ( event ) => {
            state.query = event.target.value;
            renderItems();
        } );
        root.querySelector( '[data-wpae-category]' ).addEventListener( 'change', ( event ) => {
            state.category = event.target.value;
            renderItems();
        } );
        document.addEventListener( 'keydown', handleKeydown );
    };

    const open = () => {
        mount();
        previousFocus = document.activeElement;
        root.hidden = false;
        document.body.classList.add( 'wpae-library-open' );
        root.querySelector( '[data-wpae-search]' ).focus();
        load();
    };

    window.WPAEBlockLibraryUI = {
        open,
        close,
        refresh: load,
    };
}() );
