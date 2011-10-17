// =======================================================================
// Isolate namespace.
// [Note: one could have two instances of the entire code in this fashion:
//      var wysiwygit1 = {}, wysiwygit2 = {};
//      var wysiwygit = function() {
//         ...all the code, as below...
//      }
//      wysiwygit.call(wysiwygit1);
//      wysiwygit.call(wysiwygit2);
//
//  Note further that if all one wants is two editor instances, that should 
//  probably done with editor1 = CKEDITOR... and editor2 = CKEDITOR...]
var wysiwygit = {};
(function() {
// =======================================================================
var debug = new Array();
debug[0] = false;  // Mouseover highlighting.
debug[1] = false;  // Editing -- gray-out other.
debug[2] = false;  // Saving edit data.
debug[3] = false;  // Offset measurements.
debug[4] = false;  // Styles.
debug[5] = false;  // Signals to parent.
debug[6] = false;  // Wrapping.
debug[7] = false;  // Check for lock.

var wysiwygit = this;

var highlighting = true;
var editorDiv;
var editorDivId;
var editorDivIndex;
var editingWrapDiv;
var savedEditData;
var wysiwygit_parent;
var updateLockTimer;
var diskTmpfileMtime;
var stylesheets = new Array();
var internalStyles;


// =======================================================================
$(document).ready(function() {

   // For debug.
   wysiwygit_parent = parent.document;



   // --------------------------------------------------------------------
   // Editor events.
   CKEDITOR.on('instanceCreated', function(e) {

      // Add internal style sheets.
      if (internalStyles) {
         e.editor.addCss(internalStyles);
      }

      // (Don't need to add css from original div -- original div is included 
      // in ckeditor iframe.)

      e.editor.on('destroy', function(e) {

         // Undisplay toolbar.
         $("#CKToolbarContainer").css("display", "none");

         // Turn highlighting of editable divs back on.
         highlighting = true;
      });

      e.editor.on('instanceReady', function(e) {

         // Display toolbar.
         if (debug[7]) {
            dump("[editor.on instanceReady]");
         }
         $("#CKToolbarContainer").css({"display": "block"});

         // Overwrite the default save function.
         e.editor.addCommand( "save", {
            modes : { wysiwyg: true, source: true },
            exec : function () {
               wysiwygit.save();
            }
         });

         // Even if exit without saving, unwrap will destroy scripts, so need
         // to copy data now.
         savedEditData = e.editor.getData();
         savedEditData = unwrapInnerStyleDiv(savedEditData);
      });

      /*
      e.editor.on('change', function(e) {
         if (e.editor.checkDirty()) {
            changeSinceSave = true;
         }
      });
      */
   });
   // --------------------------------------------------------------------------

   // Listen for the double click event.
   $("body").dblclick(onDoubleClick);

   // Add toolbar divs.
            //&nbsp;&times;&nbsp; \
   var toolbarDivs = '\
      <div id="CKToolbarContainer" class="CKToolbar noedit"> \
         <div id="CKToolbarTitle" class="CKToolbar noedit"> \
            &nbsp;Editing controls \
            &emsp; <span id="save_feedback"></span> \
         </div> \
         <div id="CKToolbarExit" class="CKToolbar noedit" onclick="wysiwygit.removeEditor(true)" title="Exit (with optional save)"> \
         </div> \
         <div id="CKToolbar" class="CKToolbar noedit"> \
         </div> \
         <div id="CKBottom" class="CKToolbar noedit"> \
         </div> \
      </div> \
   ';
   $("body").append(toolbarDivs);

   // Make CKEDITOR toolbar draggable.
   $("#CKToolbarContainer").draggable({
      handle:        '#CKToolbarTitle',  // Only the title 
      containment:   'document',
      iframeFix:     true,
      scroll:        false       // Whether dragging will cause page to scroll.
   });

   // Add clearer (makes divs as big as contained elements) to each div on
   // this page.  [Done in prepare_page_for_edit.php]
   // $("div[dke_element_index]").not(dke_noedit).append('<div class="wysiwygit_clearer noedit">&nbsp;</div>');

   // Add highlight divs to divs on this page.
   $("div[dke_element_index]").not(dke_noedit).append('<div class="wysiwygit_highlight noedit"></div>');

   // Add screen div to body.
   $("body").append('<div class="wysiwygit_screen" style="display: none; position: fixed;"></div>');

   // Find external style sheets.
   var stylesheet;
   $('link[rel*="style"]').each(function(i) {
      stylesheet = $(this).attr("href");
      stylesheets.push(stylesheet);
   });
   if (debug[4]) {
      dump("stylesheets: " + stylesheets.join());
   }

   // And internal style sheets.
   var style;
   var styles = new Array();
   $('style').each(function(i) {
      style = $(this).text();
      styles.push(style);
   });
   internalStyles = styles.join(' ');
   if (debug[4]) {
      dump("internalStyles: " + internalStyles);
   }

   // Highlight divs on mouseenter.
   $("div[dke_element_index]").not(dke_noedit).mouseenter(highlightDiv);

   // Remove highlighting on mouseleave().
   $("div[dke_element_index]").not(dke_noedit).mouseleave(unHighlightDiv);
});

// =======================================================================

// Let header know: no longer editing, loading new page.
window.onunload = function() {
   if (debug[5]) {
      dump("[onunload] parent.editorIsOpen: " + parent.editorIsOpen);
   }
   parent.editorIsOpen = false;
   parent.editButtonOn();
}


// -----------------------------------------------------------------------
// --------------------------------------------------------------------------
// Highlight divs on mouseenter.
var highlightDiv = function() {

   // If not highlighting, return.
   if (! highlighting) {
      return 0;
   }

   // If this is an inner div, set flags to ignore mouseenter() on
   // outer (parent) divs, and do mouseleave() on them, and set flag
   // ...

   if (debug[0]) {
      var name = this.nodeName.toLowerCase();
      var id = $(this).attr("id");
      dump("[mouseenter] id: " + id + ", name: " + name);
   }

   // If already in this div, or in a child div of this div, ignore.
   if (this.inThisDiv || this.inChildDiv) {
      if (debug[0]) {
         dump("&emsp; &emsp; Already in this div or a child.");
      }
      return 0;
   }

   // Look at parent divs.
   var element = this;
   var name;
   var zindex = 100;
   while (element) {
      element = element.parentNode;
      name = element.nodeName.toLowerCase();
      if (debug[0]) {
         var id = $(element).attr("id");
         dump("&emsp; &emsp; id: " + id + ", name: " + name);
      }
      if (name == 'div') {
         element.inThisDiv = true;
         element.inChildDiv = true;

         // Take away highlighting from parent div and all children 
         // (this particular child and its children will get 
         // re-highlighted, below).
         $(element).find(".wysiwygit_highlight").css({'display': 'none', 'z-index': 0});

         // Also, undo last offset.
         /*
         $(element).find(".wysiwygit_highlight").each(function() {
            var negLastOffset = $(this).get(0).lastOffset;
            negLastOffset.left = - negLastOffset.left;
            negLastOffset.top  = - negLastOffset.top;
            if (debug[3]) {
               dump("[mouseenter] negLastOffset: " + negLastOffset.left + ", " + negLastOffset.top);
            }
            $(this).offset(negLastOffset);
         });
         */
         $(element).find("div[dke_element_index]").css({'z-index': 0});
         $(element).css({'z-index': 0});
      }
      if (name == 'body') {
         break;
      }
   }

   // Set size and position of highlight div to that of parent.
   /*
   $(this).find(".wysiwygit_highlight").each(function() {
      var parentOffset = $(this).offsetParent().offset();
      if (debug[3]) {
         dump("[mouseenter] parentOffset: " + parentOffset.left + ", " + parentOffset.top);
      }
      $(this).offset(parentOffset);
      $(this).height($(this).parent().height());
      $(this).width($(this).parent().width());

      // Save offset in dom element.
      $(this).get(0).lastOffset = parentOffset;
   });
   */

   // Display, and raise children z-index higher.
   $(this).find(".wysiwygit_highlight").css({'display': 'block', opacity: '1.0', 'z-index': 1}).animate({opacity: '.20'}, 1000, function() {$(this).css({display: 'none'}); });
   $(this).find("div[dke_element_index]").css({'z-index': 2});
   $(this).css({'z-index': 2});
   this.inThisDiv = true;
}


// --------------------------------------------------------------------
// Remove highlighting on mouseleave().
var unHighlightDiv = function() {
   // If not highlighting, return.
   if (! highlighting) {
      return 0;
   }

   if (debug[0]) {
      var name = this.nodeName.toLowerCase();
      var id = $(this).attr("id");
      dump("[mouseleave] id: " + id + ", name: " + name);
   }

   // If not in this div, ignore.
   if (! this.inThisDiv) {
      if (debug[0]) {
         dump("&emsp; &emsp; Not in this div.");
      }
      return 0;
   }
   this.inThisDiv = false;
   this.inChildDiv = false;
   $(this).find(".wysiwygit_highlight").css({'display': 'none', 'z-index': 0});

   // Also, undo last offset.  "get" provides the dom node.
   /*
   $(this).find(".wysiwygit_highlight").each(function() {
      var negLastOffset = $(this).get(0).lastOffset;
      negLastOffset.left = - negLastOffset.left;
      negLastOffset.top  = - negLastOffset.top;
      if (debug[3]) {
         dump("[mouseleave] negLastOffset: " + negLastOffset.left + ", " + negLastOffset.top);
      }
      $(this).offset(negLastOffset);
   });
   */
   $(this).find("div[dke_element_index]").css({'z-index': 0});
   $(this).css({'z-index': 0});

   // Look at parent divs.  If haven't left one yet, show that are in
   // it.
   var element = this;
   var name;
   while (element) {
      element = element.parentNode;
      name = element.nodeName.toLowerCase();
      if (name == 'div') {
         if (debug[0]) {
            var id = $(element).attr("id");
            dump("&emsp; &emsp; id: " + id + ", name: " + name + ", inThisDiv: " + element.inThisDiv);
         }
         if (element.inThisDiv) {
            $(element).css({'z-index': '2'});

            // Highlight all children.
            $(element).find(".wysiwygit_highlight").css({display: 'block', opacity: '1.0', 'z-index': 1}).animate({opacity: '.20'}, 1000, function() {$(this).css({display: 'none'}); });
            $(element).find("div[dke_element_index]").css({'z-index': 2});
            break;
         }
      }
      if (name == 'body') {
         break;
      }
   } 
}


// -----------------------------------------------------------------------
function unwrapInnerStyleDiv(html) {
   if (debug[6]) {
      dump("[unwrapInnerStyleDiv] html: " + html);
   }

   // Style wrapper may get "co-opted" by CKEditor -- turned into an <H2, for
   // example.  Still has attribute "dke_element_index" so identify it by
   // that.  If so, don't unwrap, but do delete all style info.
   var matches = html.match(/<(\S+)[^>]*dke_element_index/);
   if (matches) {
      var tagName = matches[1];
      if (debug[6]) {
         dump("tagName: " + tagName);
      }
      if (tagName.toLowerCase() == 'div') {

         // Regular unwrap.  Remove opening div.
         html = html.replace(/<div[^>]*>/i, '');

         // Remove closing tag -- before last clearer.  Use lookahead.
         html = html.replace(/<\/div>(?=[^>]*wysiwygit_clearer[^>]*>[^<]*<\/div>\s*$)/i, '');
      } else {

         // No unwrap, but delete style info if there.
         var re = new RegExp('<' + tagName + '[^>]*style', 'i');
         matches = html.match(re);
         if (matches) {
            html = html.replace(/\s*style="[^"]*"/i, '');
         }
         // Also take away the dke_element_index attribute.
         html = html.replace(/\s*dke_element_index="\d+"/i, '');
      }
      if (debug[6]) {
         dump("[unwrapInnerStyleDiv] html: " + html);
      }

   }



   return html;
}


// -----------------------------------------------------------------------
function unwrap() {

   // Editing wrapper has id and styles of original div.  So just give it
   // the inner HTML of what we've been editing -- less the repeat of the
   // original div (which provided style info to the Ckeditor iframe).
   editingWrapDiv.innerHTML = savedEditData;

   // Do need to reset z-index.
   $(editingWrapDiv).css({'z-index': 0});

   // Put highlighting divs back into edited divs.
   $(editingWrapDiv).find("div[dke_element_index]").not('.noedit').append('<div class="wysiwygit_highlight noedit"></div>');
   $(editingWrapDiv).append('<div class="wysiwygit_highlight noedit"></div>');

   // And make sure that all will be highlighted.
   $(editingWrapDiv).find("div[dke_element_index]").not(dke_noedit).mouseenter(highlightDiv);
   $(editingWrapDiv).find("div[dke_element_index]").not(dke_noedit).mouseleave(unHighlightDiv);
   $(editingWrapDiv).mouseenter(highlightDiv);
   $(editingWrapDiv).mouseleave(unHighlightDiv);
}


// -----------------------------------------------------------------------
function onDoubleClick(ev) {

   // If already editing, ignore.
   if (this.editor) {
      return 0;
   }

   // Get the element which fired the event. This is not necessarily the
   // element to which the event has been attached.
   var element = ev.target || ev.srcElement;

   if (debug[2]) {
      dump("[onDoubleClick] element: " + element);
      dump("className: " + element.className);
      dump("className.indexOf('wysiwygit_clearer'): " + element.className.indexOf('wysiwygit_clearer'));
   }

   // Find the div that holds this element.
   var name = element.nodeName.toLowerCase();
   var className = element.className;
   while (    element 
           // Stop when we get to a div, except for wysiwygit_clearer.
           && (name != 'div' || className.indexOf('wysiwygit_clearer') == 0)
           && (name != 'body')
           && className.indexOf('cke_') != 0 ) {
      if (debug[2]) {
         dump("[onDoubleClick] nodeName: " + element.nodeName);
         dump("nodeName: " + element.nodeName);
         dump("className.indexOf('wysiwygit_clearer'): " + className.indexOf('wysiwygit_clearer'));
      }
      element = element.parentNode;
      name = element.nodeName.toLowerCase();
      className = element.className;
   }

   // Start editor for this div.
   if (debug[2]) {
      dump("[onDoubleClick] name: " + name);
   }
   if (name == 'div') {
      var className = element.className;
      if (! className || className.indexOf('noedit') == -1) {
         
         // See if has dke_element_index attribute.
         if (debug[2]) {
            dump("attr: " + $(element).attr('dke_element_index'));
         }
         if ($(element).attr('dke_element_index') != undefined) {

            // Check that page not being actively edited by another user
            // (that is, no lock file) -- will call replaceDiv() if OK to
            // edit.
            checkEditingLock(element);
         }
      }
   }
}


// -----------------------------------------------------------------------------
// Ajax call to check whether locked for editing.
function checkEditingLock(element) {
   var data = 'lckfile=' + lckfileFullPath + '&tmpfile=' + tmpfileFullPath;
   if (debug[7]) {
      dump("[checkEditingLock] data: " + data);
   }

   // --------------------------------------------
   // Closure to pass info to reloadTmpfile() and replaceDiv().
   editorDivIndex = $(element).attr('dke_element_index');
   var onCheckEditingLock = function(returnData) {

      // If locked, message.
      if (returnData[0]) { 
         $('#wysiwygit_header_message', wysiwygit_parent).html('<span style="background: yellow;">&nbsp;Cannot edit. Page is being edited by another user. <a href="javascript: lockedDialog();" style="border: 0px; text-decoration: underline; color: blue;" title="If you or another user just closed another browser window, please try again in one minute..."><span style="font-weight: bold; font-size: 75%; vertical-align: 30%;">Help</a>&nbsp;</span></span>');

         // Turn off the message in 30 seconds (back to "Doubleclick to edit").
         setTimeout("parent.DoubleclickMessageOn()", 30000);
      } else {

         // Compare time of tmpfile on disk with time of file currently loaded.
         // If disk tmpfile time more recent, someone else has saved to it,
         // and need to reload.  Give a couple of seconds leeway.
         diskTmpfileMtime = returnData[1];
         if (debug[7]) {
            dump('[onCheckEditingLock] editorDivIndex: ' + editorDivIndex + ', diskTmpfileMtime: ' + diskTmpfileMtime + ', tmpfileMtime: ' + tmpfileMtime);
         }
         if (diskTmpfileMtime > tmpfileMtime+2) {

            // Yes, more recent.  Need to reload.  Have header routine do work
            // so can proceed to replaceDiv(().  Too much to expect div node
            // to survive page reload -- use dke_element_index to identify
            // editorDiv.
            parent.reloadTmpfile(editorDivIndex, tmpfileUrl);
         } else {
            wysiwygit.replaceDiv(element);
         }
      }
   }
   // --------------------------------------------

   $.ajax({
            type:          'POST',
            url:           scriptRelativePath + '/check_lock_file.php',
            data:          data,
            success:       onCheckEditingLock,
            dataType:      'json'        // Type of data returned by server.
   });
}


// -----------------------------------------------------------------------
// Ajax call to keep lock file current.
this.updateLock = function() {

   var data = 'lckFile=' + lckfileFullPath;
   if (debug[7]) {
      dump("[updateLock] data: " + data);
   }
   $.ajax({
            type:          'POST',
            url:           scriptRelativePath + '/update_lock_file.php',
            data:          data
   });
}


// -----------------------------------------------------------------------
this.replaceDiv = function(div, dkeEditorDivIndex) {
   if (debug[7]) {
      dump('[replaceDiv] div: ' + div + ', dkeEditorDivIndex: ' + dkeEditorDivIndex);
   }
   if (this.editor) {
      this.removeEditor();
   }

   // Start process to keep lock file current.
   updateLockTimer = setInterval("wysiwygit.updateLock()", lckUpdateInterval);

   var editingMessage = '&nbsp;<b>Editing</b>';
   if (dkeEditorDivIndex != undefined) {

      // Use index to find div; otherwise, go from dom element.
      editorDiv = $('div[dke_element_index=' + dkeEditorDivIndex + ']').get(0);
      editorDivId = editorDiv.id;
      editorDivIndex = dkeEditorDivIndex;
      editingMessage += '&nbsp; Note: recent changes by another user are included.';
   } else {
      editorDiv = div;
      editorDivId = editorDiv.id;
      editorDivIndex = $(editorDiv).attr('dke_element_index');
   }

   // Don't let highlight divs show up in editing source.
   $(editorDiv).find(".wysiwygit_highlight").remove(); 

   // Screen rest of page.
   $(".wysiwygit_screen").css({"display": "block"});

   // Separately screen each parent div of this div (because they have to
   // be in front of the whole-page screen in order for this div to be
   // in front).
   screenParents(editorDiv);

   // Turn off highlighting while editing.
   highlighting = false;

   // Wrap div to edit in two divs (!).  Outermost div will wrap everything
   // (including Ckeditor's div replacement) for z-index positioning purposes.
   // Middle div will become div to be replaced.  Original (innermost) div will
   // provide any external stylesheet information connected with div to 
   // Ckeditor's iframe body.

   // Get height and width (for editor) before mess around with things.
   var editorHeight = $(editorDiv).height();
   var editorWidth = $(editorDiv).width();

   // Use jQuery for wrap, but if there are any in-line scripts, save and 
   // restore inner html sans jQuery, which has great trouble with any such
   // scripts.
   var divHtml = editorDiv.innerHTML;
   var divHtmlHasScript = !! divHtml.match(/<script/i);
   if (divHtmlHasScript) {
      if (debug[6]) {
         dump('[replaceDiv] deleting innerHTML...');
      }
      editorDiv.innerHTML = '';
   }

   // Outermost div.  Outermost div gets all of the styles of the div to be 
   // edited.  (Actually provides just margin, border, and padding around 
   // iframe.)
   $(editorDiv).css({"z-index": 95});

   var divStyles = $(editorDiv).attr("style");
   var styleAttr = divStyles ? ' style="' + divStyles + '"' : '';

   var divClasses = $(editorDiv).attr("class");
   var classAttr = divClasses ? ' class="' + divClasses + '"' : '';

   $(editorDiv).wrap('<div id="dke_editingWrap"' + styleAttr + classAttr + '>');
   editingWrapDiv = $('#dke_editingWrap').get(0);

   // Middle div.
   $(editorDiv).wrap('<div id="dke_newEditorDiv">');
   var newEditorDiv = $('#dke_newEditorDiv').get(0);

   // If needed, put content back into original div.
   if (divHtmlHasScript) {
      editorDiv.innerHTML = divHtml;
   }

   // Move the last clearer outside of this div.
   $(editorDiv).find('div[id*=wysiwygit_clearer]').last().appendTo($(newEditorDiv));

   // In order for style inheritance to be complete on wrapping div, it has
   // to have the id of the original div.  Original div -- which will end up
   // inside the Ckeditor iframe -- also needs to have that id in order to
   // receive style info.  Original div should not have borders, margin, or
   // padding now.  Also, do not include visibility.  Reset that first.
   if (divStyles) {
      if (debug[4]) {
         dump("[replaceDiv] divStyles: " + divStyles);
      }
      divStyles = divStyles.replace(/visibility/i, 'nothing');
      divStyles = divStyles.replace(/margin/ig, 'nothing');
      divStyles = divStyles.replace(/border/ig, 'nothing');
      divStyles = divStyles.replace(/padding/ig, 'nothing');

      // More: cannot be positioned absolutely within editor window (for one
      // thing, the new editor div will not know of content's height, so 
      // autogrow won't work properly), and positioning info should be 
      // discarded (the wrapping div is already so positioned).
      divStyles = divStyles.replace(/position/ig, 'nothing');
      divStyles = divStyles.replace(/([;\s^])left\s*:/ig, '$1nothing:');
      divStyles = divStyles.replace(/([;\s^])right\s*:/ig, '$1nothing:');
      divStyles = divStyles.replace(/([;\s^])top\s*:/ig, '$1nothing:');
      divStyles = divStyles.replace(/([;\s^])bottom\s*:/ig, '$1nothing:');
   }

   // Make margins, borders, and padding explicitly zero any any case
   // (override stylesheet styles if present).
   divStyles += "; margin: 0px; border: 0px; padding: 0px; ";

   // And make position relative, with no further positioning.
   divStyles += "position: relative; left: 0px; right: 0px; top: 0px; bottom: 0px; ";
   if (debug[4]) {
      dump("[replaceDiv] divStyles: " + divStyles);
   }

   // Reset style on div.
   $(editorDiv).attr('style', divStyles);

   // Middle div is one that will be replaced.
   originalEditorDiv = editorDiv;
   editorDiv = newEditorDiv;
   if (debug[6]) {
      dump("[replaceDiv] editorDiv: " + editorDiv);
   }

   // Outermost div gets same id as original div and gets same 
   // dke_element_index, since ultimately it will be the replacement for the
   // original div.
   editingWrapDiv.id = editorDivId;
   $(editingWrapDiv).attr('dke_element_index', editorDivIndex);

   var dke_toolbar =
   [
      { name: 'document',     items : ['Source', '-', 'Save', 'DocProps', 'Templates'] },
      { name: 'clipboard',    items : ['Cut','Copy','Paste','PasteText','PasteFromWord','-','Undo','Redo'] },
      { name: 'editing',      items : ['Find','Replace'] },
      { name: 'insert',       items : ['Image','Table', 'HorizontalRule', 'SpecialChar'] },
      '/',
      { name: 'basicstyles',  items : ['Bold', 'Italic', 'Underline', 'Strike', 'Subscript', 'Superscript', '-', 'RemoveFormat'] },
      { name: 'paragraph',    items : ['NumberedList', 'BulletedList', '-', 'Outdent', 'Indent', '-', 'Blockquote', '-', 'JustifyLeft', 'JustifyCenter', 'JustifyRight', 'JustifyBlock', '-'] },
      { name: 'links',        items : ['Link', 'Unlink', 'Anchor'] },
      '/',
      { name: 'styles',       items : ['Styles', 'Font', 'FontSize'] },
      { name: 'colors',       items : ['TextColor', 'BGColor'] },
      { name: 'tools',        items : ['ShowBlocks', '-', 'About'] }
   
   ];
   // Javascript is protected by default.
   // PHP code, "clearer" divs.  Doesn't seem to work in any reasonable form,
   // so not using.  TRYING: php
   var protect = 
   [
      /<\?[\s\S]*?\?>/g,               
   ];

   this.editor = CKEDITOR.replace(editorDiv, { 
                  sharedSpaces : { top :    'CKToolbar',
                                   bottom : 'CKBottom'
                                 }, 
                  extraPlugins :              'autogrow',
                  autoGrow_minHeight :        12,
                  skin :                      'office2003',
                  disableNativeSpellChecker : false,
                  protectedSource :           protect,
                  contentsCss :               stylesheets,
                  bodyId :                    'cke_body',
                  removePlugins :             'resize',
                  height :                    editorHeight,
                  width :                     editorWidth,
                  toolbar :                   'dke_toolbar',
                  toolbar_dke_toolbar :       dke_toolbar,
                  entities_processNumerical : true,
                  startupShowBorders :        false,
                  filebrowserWindowHeight :   '100%',
                  filebrowserBrowseUrl :      scriptRelativePath + '/kcfinder/browse.php',
                  filebrowserImageBrowseUrl : scriptRelativePath + '/kcfinder/browse.php',
                  filebrowserUploadUrl :      scriptRelativePath + '/kcfinder/upload.php',
                  filebrowserImageUploadUrl : scriptRelativePath + '/kcfinder/upload.php'

   });
   if (debug[2]) {
      dump("[replaceDiv] editor: " + this.editor);
   }
   $('#wysiwygit_header_message', wysiwygit_parent).html(editingMessage);
}


// -----------------------------------------------------------------------
function screenParents(div) {
   var element = div;
   var name;
   var zindex = 100;
   if (debug[1]) {
      dump("[screenParents]");
   }
   while (element) {
      element = element.parentNode;
      name = element.nodeName.toLowerCase();
      if (debug[1]) {
         var id = $(element).attr("id");
         dump("&emsp; &emsp; id: " + id + ", name: " + name);
      }
      if (name == 'div') {

         // Add screen to parent div.  Need each parent lower than this.
         $(element).append('<div class="wysiwygit_screen"></div>');
         zindex--;
         $(element).css({"z-index": zindex});
      }
      if (name == 'body') {
         break;
      }
   }
      
   $(this).css({'z-index': '2'});
}


// -----------------------------------------------------------------------
// Update both tmp file and original file.
this.save = function(exiting) {

   // Initial feedback.
   $("#save_feedback").html("Saving...");

   // Update the original div (because may exit without saving later).
   savedEditData = this.editor.getData();
   savedEditData = unwrapInnerStyleDiv(savedEditData);
   this.editor.resetDirty();
   if (debug[2]) {
      dump("[save] savedEditData: " + savedEditData);
   }
   editorDiv.innerHTML = savedEditData;

   // File names created by script at end of body (appended by
   // prepare_page_for_edit.php).
   var data = 'editData=' + encodeURIComponent(savedEditData) 
              + '&editDivIndex=' + editorDivIndex 
              + '&fullPath=' + fullPath 
              + '&tmpfileFullPath=' + tmpfileFullPath;
   if (exiting) {
      data = data + '&lckfileFullPath=' + lckfileFullPath;
   }

   if (debug[2]) {
      dump("[save] data: " + data);
   }
   $.ajax({
            type:          'POST',
            url:           scriptRelativePath + '/update_edited_file.php',
            data:          data,
            success:       onSave,
            contentType:   'application/x-www-form-urlencoded; charset=utf-8',
            dataType:      'json'        // Type of data returned by server.
   });
}


// -----------------------------------------------------------------------
function onSave(returnData) {
   var saveText = 'Saved at ' + returnData[0];

   var errmsg = returnData[1];
   if (errmsg) {
      errmsg = errmsg.replace(/"/g, '&quot;').replace(/'/g, '&#39');
      saveText  = '<a href="javascript: void()" '
                +    'title="' + errmsg + '" '
                +    'style="color: red; background: white;">(Save error)</a>';
   }
   $("#save_feedback").html(saveText);

   tmpfileMtime = returnData[2];

   this.saveComplete = true;
}


// -----------------------------------------------------------------------
this.removeEditor = function(confirmB) {

   var exitWithSave = false;
   if (confirmB) {
      if (this.editor.checkDirty()) {
         if (confirm("Changes have not been saved.  Save now?  Click Cancel to exit without saving")) {
            exitWithSave = true;
            this.save(true);
         }
      }
   }

   // Destroy the editor -- do not update div.  (Update only when save.)
   this.editor.destroy(true);
   this.editor = null;

   // Set the header message back to "Double-click section to edit".
   parent.DoubleclickMessageOn();

   // Stop updating lock file timer, remove lock file.
   clearInterval(updateLockTimer);

   // Save-with-exiting-true already removed lock file.  Remove lock file now
   // if no save.
   if (! exitWithSave) {
      this.removeLockFile();
   }

   // Unwrap divs.
   unwrap();

   // Hide full-page screen, unscreen parents.
   $(".wysiwygit_screen").css({"display": "none"});
   unScreenParents(editingWrapDiv);
}
  

// -----------------------------------------------------------------------
this.removeLockFile = function(callBack) {
   var data = 'lckFile=' + lckfileFullPath;
   var callBackFunction;

   if (debug[7]) {
      dump("[removeLockFile] callBack: " + callBack + ", data: " + data);
   }
   if (callBack) {
      callBackFunction = function() {
         eval(callBack);
      }
   }
   $.ajax({
            type:          'POST',
            url:           scriptRelativePath + '/remove_lock_file.php',
            data:          data,
            success:       callBackFunction
   });
}


// -----------------------------------------------------------------------
function unScreenParents(div) {
   var element = div;
   var name;
   if (debug[1]) {
      dump("[unScreenParents]");
   }
   while (element) {
      element = element.parentNode;
      name = element.nodeName.toLowerCase();
      if (debug[1]) {
         var id = $(element).attr("id");
         dump("&emsp; &emsp; id: " + id + ", name: " + name);
      }
      if (name == 'div') {
         $(element).find(".wysiwygit_screen").remove();
         $(element).css({'z-index': 0});
      }
      if (name == 'body') {
         break;
      }
   }
}


// -----------------------------------------------------------------------
(function($) {
    /* jQuery object extension methods */
    $.fn.extend({
    	appendText: function(e) {
    		if ( typeof e == "string" )
				return this.append( document.createTextNode( e ) );
			return this;
    	}
    });


})(jQuery);


// -----------------------------------------------------------------------
function dump(data) {
   $('#wysiwygit_dump', wysiwygit_parent).appendText(data);
   $('#wysiwygit_dump', wysiwygit_parent).append('<br />');

   // Scroll to bottom.
   $('#wysiwygit_dump', wysiwygit_parent).attr('scrollTop', $('#wysiwygit_dump', wysiwygit_parent).attr('scrollHeight'));
}


// =======================================================================
// Isolate namespace.
}).apply(wysiwygit);

