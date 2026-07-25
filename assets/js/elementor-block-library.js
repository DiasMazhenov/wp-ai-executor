( function () {
    'use strict';

    const config = window.WPAEBlockLibrary;
    if ( ! config || ! config.endpoint ) {
        return;
    }

    const notify = ( message, type = 'success' ) => {
        if ( window.elementor && elementor.notifications && elementor.notifications.showToast ) {
            elementor.notifications.showToast( {
                message,
                type,
            } );
            return;
        }
        if ( type === 'error' ) {
            window.alert( message );
        }
    };

    const serializeModel = ( model ) => {
        const data = model && model.toJSON ? model.toJSON() : {};
        const children = model && model.get ? model.get( 'elements' ) : null;
        if ( children && Array.isArray( children.models ) ) {
            data.elements = children.models.map( serializeModel );
        } else if ( ! Array.isArray( data.elements ) ) {
            data.elements = [];
        }
        return data;
    };

    const copyText = async ( value ) => {
        if ( navigator.clipboard && window.isSecureContext ) {
            await navigator.clipboard.writeText( value );
            return;
        }
        const textarea = document.createElement( 'textarea' );
        textarea.value = value;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild( textarea );
        textarea.select();
        document.execCommand( 'copy' );
        textarea.remove();
    };

    const currentDocumentId = () => {
        const document = window.elementor &&
            elementor.documents &&
            elementor.documents.currentDocument;
        return document && document.id ? Number( document.id ) : 0;
    };

    const saveBlock = async ( element, model ) => {
        const fallbackTitle = `${ element.elType || 'element' } ${ element.id || '' }`.trim();
        const title = window.prompt( config.strings.titlePrompt, fallbackTitle );
        if ( title === null ) {
            return;
        }
        const category = window.prompt( config.strings.categoryPrompt, 'custom' );
        if ( category === null ) {
            return;
        }

        const response = await window.fetch( config.endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': config.nonce,
            },
            body: JSON.stringify( {
                title,
                category,
                source: 'local',
                source_post_id: currentDocumentId(),
                elementor_version: window.elementor && elementor.config ? elementor.config.version : '',
                block: element,
                editor_element_id: model && model.id ? model.id : '',
            } ),
        } );
        const responseText = await response.text();
        let payload = {};
        try {
            payload = responseText ? JSON.parse( responseText ) : {};
        } catch ( error ) {
            throw new Error( responseText || config.strings.failed );
        }
        if ( ! response.ok || ! payload.ok ) {
            throw new Error( payload.error || config.strings.failed );
        }
        notify( config.strings.saved );
    };

    const addActions = ( groups, view ) => {
        if ( ! view || ! view.model ) {
            return groups;
        }
        if ( groups.some( ( group ) => group.name === 'wpae-block-library' ) ) {
            return groups;
        }

        groups.push( {
            name: 'wpae-block-library',
            actions: [
                {
                    name: 'wpae-copy-json',
                    icon: 'eicon-code',
                    title: config.strings.copy,
                    isEnabled: () => true,
                    callback: async () => {
                        try {
                            await copyText( JSON.stringify( serializeModel( view.model ), null, 2 ) );
                            notify( config.strings.copied );
                        } catch ( error ) {
                            notify( error.message || config.strings.failed, 'error' );
                        }
                    },
                },
                {
                    name: 'wpae-save-library',
                    icon: 'eicon-library-save',
                    title: config.strings.save,
                    isEnabled: () => true,
                    callback: async () => {
                        try {
                            await saveBlock( serializeModel( view.model ), view.model );
                        } catch ( error ) {
                            notify( error.message || config.strings.failed, 'error' );
                        }
                    },
                },
            ],
        } );
        return groups;
    };

    window.addEventListener( 'elementor/init', () => {
        [ 'container', 'widget', 'section', 'column' ].forEach( ( elementType ) => {
            elementor.hooks.addFilter(
                `elements/${ elementType }/contextMenuGroups`,
                addActions
            );
        } );
    } );
}() );
