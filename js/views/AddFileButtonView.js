'use strict';

var
	Utils = require('%PathToCoreWebclientModule%/js/utils/Common.js'),
	TextUtils = require('%PathToCoreWebclientModule%/js/utils/Text.js'),

	Popups = require('%PathToCoreWebclientModule%/js/Popups.js'),

	CreateDocumentPopup = require('modules/%ModuleName%/js/popups/CreateDocumentPopup.js')
;

/**
 * @constructor
 */
function CAddFileButtonView()
{
	this.createDocumentCommand = Utils.createCommand(this, this.createDocument, function () {
		return	true;
	});
	this.createSpreadSheetCommand = Utils.createCommand(this, this.createSpreadSheet, function () {
		return	false;
	});
	this.createPresentationCommand = Utils.createCommand(this, this.createPresentation, function () {
		return	true;
	});
}

CAddFileButtonView.prototype.ViewTemplate = '%ModuleName%_AddFileButtonView';

CAddFileButtonView.prototype.createDocument = function ()
{
	Popups.showPopup(CreateDocumentPopup,
		[TextUtils.i18n('%MODULENAME%/LABEL_BLANK_DOCUMENT_NAME'), this.createDocumentWithName.bind(this)]);
};

CAddFileButtonView.prototype.createDocumentWithName = function (sBlankName)
{
	console.log('sBlankName', sBlankName);
};

CAddFileButtonView.prototype.createSpreadSheet = function ()
{
	Popups.showPopup(CreateDocumentPopup,
		[TextUtils.i18n('%MODULENAME%/LABEL_BLANK_SPREADSHEET_NAME'), this.createDocumentWithName.bind(this)]);
};

CAddFileButtonView.prototype.createPresentation = function ()
{
	Popups.showPopup(CreateDocumentPopup,
		[TextUtils.i18n('%MODULENAME%/LABEL_BLANK_PRESENTATION_NAME'), this.createDocumentWithName.bind(this)]);
};

module.exports = new CAddFileButtonView();
