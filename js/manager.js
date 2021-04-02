'use strict';

module.exports = function (oAppData) {
	var
		_ = require('underscore'),
		$ = require('jquery'),
		moment = require('moment'),

		TextUtils = require('%PathToCoreWebclientModule%/js/utils/Text.js'),
		Types = require('%PathToCoreWebclientModule%/js/utils/Types.js'),
		UrlUtils = require('%PathToCoreWebclientModule%/js/utils/Url.js'),

		Ajax = require('%PathToCoreWebclientModule%/js/Ajax.js'),
		App = require('%PathToCoreWebclientModule%/js/App.js'),
		Screens = require('%PathToCoreWebclientModule%/js/Screens.js'),

		WindowOpener = require('%PathToCoreWebclientModule%/js/WindowOpener.js'),

		CAbstractFileModel = require('%PathToCoreWebclientModule%/js/models/CAbstractFileModel.js'),

		oOpenedWindows = {},
		oSyncStartedMoments = {},
		iCheckWindowsInterval = 0
	;

	function checkOpenedWindows()
	{
		_.each(oOpenedWindows, function (oData, sFullPath) {
			var
				oWin = oData['Win']
				// oFile = oData['File']
			;
			if (oWin.closed)
			{
				oSyncStartedMoments[sFullPath] = moment();
				delete oOpenedWindows[sFullPath];
				// oFile._editor_setCheckChangesTimer();
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

	// function isEditEnded(oFile, oResponseResult)
	// {
	// 	if (oFile.oExtendedProps.LastEdited && oResponseResult.ExtendedProps.LastEdited)
	// 	{
	// 		return oFile.oExtendedProps.LastEdited !== oResponseResult.ExtendedProps.LastEdited;
	// 	}
	// 	return oResponseResult.LastModified !== oFile.iLastModified;
	// }
	//
	// function isSyncTimeNotExpired(sFullPath)
	// {
	// 	return oSyncStartedMoments[sFullPath] && moment().diff(oSyncStartedMoments[sFullPath]) < 20000;
	// }

	if (App.isUserNormalOrTenant())
	{
		return {
			start: function (ModulesManager) {
				var aExtensionsToView = oAppData['%ModuleName%'] ? oAppData['%ModuleName%']['ExtensionsToView'] : [];
				CAbstractFileModel.addViewExtensions(aExtensionsToView);
				App.subscribeEvent('FilesWebclient::ConstructView::after', function (oParams) {
					if (oParams.Name === 'CFilesView') {
						var oView = oParams.View;
						if (oView && _.isFunction(oView.registerCreateButtonsController))
						{
							var CAddFileButtonView = require('modules/%ModuleName%/js/views/CAddFileButtonView.js');
							oView.registerCreateButtonsController(new CAddFileButtonView(oView.storageType, oView.currentPath));
						}
					}
				});
				App.subscribeEvent('FilesWebclient::ParseFile::after', function (aParams) {
					var
						oFile = aParams[0],
						oRawData = aParams[1]
					;

					if (oFile.hasAction('view') && oFile.oActionsData['view'] && -1 !== $.inArray(oFile.extension(), aExtensionsToView))
					{
						delete oFile.oActionsData['view'].HandlerName;
						oFile.oActionsData['view'].Handler = function () {
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
						}.bind(oFile);
					}
					if (oFile.hasAction('edit'))
					{
						oFile.removeAction('edit');
						if (oFile.oActionsData['edit'])
						{
							oFile.actions.unshift('edit');
							oFile.oActionsData['edit'].Text = TextUtils.i18n('%MODULENAME%/ACTION_EDIT_FILE');
							oFile.oActionsData['edit'].Handler = function () {
								if (oOpenedWindows[oFile.fullPath()] && !oOpenedWindows[oFile.fullPath()].Win.closed)
								{
									oOpenedWindows[oFile.fullPath()].Win.focus();
								}
								// else if (isSyncTimeNotExpired(oFile.fullPath()))
								// {
								// 	Screens.showReport(TextUtils.i18n('%MODULENAME%/REPORT_WAIT_UNTIL_FILE_SYNCED'));
								// 	if (!this._editor_iCheckChangesTimer)
								// 	{
								// 		oFile._editor_setCheckChangesTimer();
								// 	}
								// }
								else
								{
									var
										oWin = null,
										sUrl = UrlUtils.getAppPath() + this.getActionUrl('edit') + '/' + moment().unix()
									;
									if (Types.isNonEmptyString(sUrl) && sUrl !== '#')
									{
										oWin = WindowOpener.open(sUrl, sUrl, false);
										if (oWin)
										{
											addOpenedWindow(oFile, oWin)
											oWin.focus();
										}
									}
								}
							}.bind(oFile);
							// oFile._editor_setCheckChangesTimer = function () {
							// 	clearTimeout(this._editor_iCheckChangesTimer);
							// 	this._editor_iCheckChangesTimer = setTimeout(this._editor_checkChanges, 1000);
							// }.bind(oFile);
							// oFile._editor_checkChanges = function () {
							// 	clearTimeout(this._editor_iCheckChangesTimer);
							// 	Ajax.send('Files', 'GetFileInfo', {
							// 		'UserId': App.getUserId(),
							// 		'Type': this.storageType(),
							// 		'Path': this.path(),
							// 		'Id': this.fileName()
							// 	}, function (oResponse) {
							// 		var bEditedEnded = isEditEnded(this, oResponse.Result);
							// 		if (!bEditedEnded && isSyncTimeNotExpired(this.fullPath()))
							// 		{
							// 			oFile._editor_setCheckChangesTimer();
							// 		}
							// 		else
							// 		{
							// 			delete this._editor_iCheckChangesTimer;
							// 			delete oSyncStartedMoments[this.fullPath()];
							// 			if (bEditedEnded)
							// 			{
							// 				ModulesManager.run('FilesWebclient', 'refresh');
							// 				Screens.showReport(TextUtils.i18n('%MODULENAME%/REPORT_FILE_SYNCED_SUCCESSFULLY'));
							// 			}
							// 		}
							// 	}, this);
							// }.bind(oFile);
						}
						if (oFile.hasAction('view'))
						{
							oFile.removeAction('view');
							oFile.actions.push('view');
						}
					}
				});
			}
		};
	}

	return null;
};
