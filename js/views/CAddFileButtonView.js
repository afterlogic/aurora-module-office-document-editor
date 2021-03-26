'use strict';

var
	_ = require('underscore'),
	ko = require('knockout'),

	Utils = require('%PathToCoreWebclientModule%/js/utils/Common.js'),
	TextUtils = require('%PathToCoreWebclientModule%/js/utils/Text.js'),

	Ajax = require('%PathToCoreWebclientModule%/js/Ajax.js'),
	Api = require('%PathToCoreWebclientModule%/js/Api.js'),
	ModulesManager = require('%PathToCoreWebclientModule%/js/ModulesManager.js'),
	Popups = require('%PathToCoreWebclientModule%/js/Popups.js'),

	CFileModel = ModulesManager.run('FilesWebclient', 'getFileConstructor'),

	CreateDocumentPopup = require('modules/%ModuleName%/js/popups/CreateDocumentPopup.js')
;

/**
 * @constructor
 */
function CAddFileButtonView(koStorageType, koCurrentPath)
{
	this.storageType = _.isFunction(koStorageType) ? koStorageType : ko.observable('');
	this.currentPath = _.isFunction(koCurrentPath) ? koCurrentPath : ko.observable('');

	this.allowCreateItems = ko.computed(function () {
		return	this.storageType() !== Enums.FileStorageType.Encrypted && this.storageType() !== Enums.FileStorageType.Shared;
	}, this);
	this.createDocumentCommand = Utils.createCommand(this, this.createDocument, this.allowCreateItems);
	this.createSpreadSheetCommand = Utils.createCommand(this, this.createSpreadSheet, this.allowCreateItems);
	this.createPresentationCommand = Utils.createCommand(this, this.createPresentation, this.allowCreateItems);
}

CAddFileButtonView.prototype.ViewTemplate = '%ModuleName%_AddFileButtonView';

CAddFileButtonView.prototype.createDocument = function ()
{
	Popups.showPopup(CreateDocumentPopup,
		[TextUtils.i18n('%MODULENAME%/LABEL_BLANK_DOCUMENT_NAME'), 'docx', this.createDocumentWithName.bind(this)]);
};

CAddFileButtonView.prototype.createDocumentWithName = function (sBlankName, sExtension)
{
	sBlankName = $.trim(sBlankName);
	if (!Utils.validateFileOrFolderName(sBlankName))
	{
		return TextUtils.i18n('FILESWEBCLIENT/ERROR_INVALID_FILE_NAME');
	}
	else
	{
		Ajax.send('%ModuleName%', 'CreateBlankDocument', {
			'Type': this.storageType(),
			'Path': this.currentPath(),
			'FileName': sBlankName + '.' + sExtension
		}, this.onCreateBlankDocumentResponse, this);
	}

	return '';
};

CAddFileButtonView.prototype.onCreateBlankDocumentResponse = function (oResponse)
{
	if (oResponse && oResponse.Result)
	{
		var
			oFile = new CFileModel(oResponse.Result)
		;
		if (oFile.path() === this.currentPath() && oFile.storageType() === this.storageType())
		{
			ModulesManager.run('FilesWebclient', 'addFileToCurrentFolder', [oFile]);
		}
		ModulesManager.run('FilesWebclient', 'refresh');
		oFile.executeAction('edit');
	}
	else
	{
		Api.showErrorByCode(oResponse);
	}
};

CAddFileButtonView.prototype.createSpreadSheet = function ()
{
	Popups.showPopup(CreateDocumentPopup,
		[TextUtils.i18n('%MODULENAME%/LABEL_BLANK_SPREADSHEET_NAME'), 'xlsx', this.createDocumentWithName.bind(this)]);
};

CAddFileButtonView.prototype.createPresentation = function ()
{
	Popups.showPopup(CreateDocumentPopup,
		[TextUtils.i18n('%MODULENAME%/LABEL_BLANK_PRESENTATION_NAME'), 'pptx', this.createDocumentWithName.bind(this)]);
};

module.exports = CAddFileButtonView;
