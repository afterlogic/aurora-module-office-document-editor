'use strict';

var
	_ = require('underscore'),
	moment = require('moment'),

	Types = require('%PathToCoreWebclientModule%/js/utils/Types.js'),
	UrlUtils = require('%PathToCoreWebclientModule%/js/utils/Url.js'),

	Ajax = require('%PathToCoreWebclientModule%/js/Ajax.js'),
	Api = require('%PathToCoreWebclientModule%/js/Api.js'),
	ModulesManager = require('%PathToCoreWebclientModule%/js/ModulesManager.js'),
	Popups = require('%PathToCoreWebclientModule%/js/Popups.js'),
	WindowOpener = require('%PathToCoreWebclientModule%/js/WindowOpener.js'),

	CFileModel = ModulesManager.run('FilesWebclient', 'getFileConstructor'),

	ConvertPopup = require('modules/%ModuleName%/js/popups/ConvertPopup.js'),

	oOpenedWindows = {},
	oSyncStartedMoments = {},
	iCheckWindowsInterval = 0,

	FilesActions = {}
;

function checkOpenedWindows()
{
	_.each(oOpenedWindows, function (oData, sFullPath) {
		var oWin = oData['Win'];
		if (oWin.closed)
		{
			oSyncStartedMoments[sFullPath] = moment();
			delete oOpenedWindows[sFullPath];
		}
	});
	if (_.isEmpty(oOpenedWindows))
	{
		clearInterval(iCheckWindowsInterval);
	}
}

function addOpenedWindow(oFile, oWin)
{
	var sFullPath = oFile.fullPath();
	oOpenedWindows[sFullPath] = {
		'Win': oWin,
		'File': oFile
	};
	clearInterval(iCheckWindowsInterval);
	iCheckWindowsInterval = setInterval(function () {
		checkOpenedWindows();
	}, 500);
}

FilesActions.view = function () {
	var
		oWin = null,
		sUrl = UrlUtils.getAppPath() + this.getActionUrl('view') + '/' + moment().unix()
	;
	if (Types.isNonEmptyString(sUrl) && sUrl !== '#')
	{
		oWin = WindowOpener.open(sUrl, sUrl, false);
		if (oWin)
		{
			oWin.focus();
		}
	}
};

FilesActions.edit = function () {
	if (oOpenedWindows[this.fullPath()] && !oOpenedWindows[this.fullPath()].Win.closed) {
		oOpenedWindows[this.fullPath()].Win.focus();
	} else {
		var
			oWin = null,
			sUrl = this.getActionUrl('edit')
		;
		if (Types.isNonEmptyString(sUrl) && sUrl !== '#') {
			if (sUrl === 'convert') {

			} else {
				sUrl = UrlUtils.getAppPath() + sUrl + '/' + moment().unix()
				oWin = WindowOpener.open(sUrl, sUrl, false);
				if (oWin) {
					addOpenedWindow(this, oWin)
					oWin.focus();
				}
			}
		}
	}
};

function convertFile () {
	Ajax.send('%ModuleName%', 'ConvertDocument', {
		'Type': this.storageType(),
		'Path': this.path(),
		'FileName': this.fileName()
	}, function (oResponse) {
		if (oResponse && oResponse.Result)
		{
			var oFile = new CFileModel(oResponse.Result);
			ModulesManager.run('FilesWebclient', 'refresh');
			oFile.executeAction('edit');
		}
		else
		{
			Api.showErrorByCode(oResponse);
		}
	}, this);
};

FilesActions.convert = function () {
	Popups.showPopup(ConvertPopup, [convertFile.bind(this), FilesActions.view.bind(this)]);
};

module.exports = FilesActions;
