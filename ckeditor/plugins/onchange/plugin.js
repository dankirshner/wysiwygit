/*
 * @file change event plugin for CKEditor
 * Copyright (C) 2011 Alfonso Martínez de Lizarrondo
 *
 * == BEGIN LICENSE ==
 *
 * Licensed under the terms of any of the following licenses at your
 * choice:
 *
 *  - GNU General Public License Version 2 or later (the "GPL")
 *    http://www.gnu.org/licenses/gpl.html
 *
 *  - GNU Lesser General Public License Version 2.1 or later (the "LGPL")
 *    http://www.gnu.org/licenses/lgpl.html
 *
 *  - Mozilla Public License Version 1.1 or later (the "MPL")
 *    http://www.mozilla.org/MPL/MPL-1.1.html
 *
 * == END LICENSE ==
 *
 */

 // Keeps track of changes to the content and fires a "change" event
CKEDITOR.plugins.add( 'onchange',
{
	init : function( editor )
	{
		// Test:
//		editor.on( 'change', function(e) { console.log(e) });

		var timer;
		// Avoid firing the event too often
		function somethingChanged()
		{
			if (timer)
				return;

			timer = setTimeout( function() {
				timer = 0;
				editor.fire( 'change' );
			}, editor.config.minimumChangeMilliseconds || 100);
		}

		// Set several listeners to watch for changes to the content
		editor.on( 'saveSnapshot', somethingChanged);


		editor.getCommand('undo').on( 'afterUndo', somethingChanged);
		editor.getCommand('redo').on( 'afterRedo', somethingChanged);

                function isSpecialKey(keyCode) 
                  {
                     // Shift, Alt, Esc, Page Up, Page Down, End, Home, Left, Up, Right, 
                     // Down (33-40, respectively), and Insert (45).
                     if (keyCode == 16 || keyCode == 18 || keyCode == 27
                           || (keyCode >= 33 && keyCode <=40) || keyCode == 45) {
                        return true;
                     } else {
                        return false;
                     }
                  }

		// Changes in WYSIWYG mode
		editor.on( 'contentDom', function()
			{
				editor.document.on( 'keydown', function( event )
					{
						// Do not capture CTRL hotkeys.
						if ( !event.data.$.ctrlKey && !event.data.$.metaKey && !isSpecialKey(event.data.$.keyCode) )
							somethingChanged();
					});

					// Firefox OK
				editor.document.on( 'drop', somethingChanged);
					// IE OK
				editor.document.getBody().on( 'drop', somethingChanged);
			});

		// Detect changes in source mode
		editor.on( 'mode', function( e )
			{
				if ( editor.mode != 'source' )
					return;

				editor.textarea.on( 'keydown', function( event )
					{
						// Do not capture CTRL hotkeys.
						if ( !event.data.$.ctrlKey && !event.data.$.metaKey  && !isSpecialKey(event.data.$.keyCode) )
							somethingChanged();
					});

				editor.textarea.on( 'drop', somethingChanged);
				editor.textarea.on( 'input', somethingChanged);
			});

		editor.on( 'afterCommandExec', function( event )
		{
			if ( event.data.name == 'source' || event.data.name == 'save' )
				return;

			if ( event.data.command.canUndo !== false )
				somethingChanged();
		} );


	} //Init
} );
