'use strict';

module.exports = function (oAppData) {
	var
		_ = require('underscore'),
		moment = require('moment'),

		TextUtils = require('%PathToCoreWebclientModule%/js/utils/Text.js'),
		Types = require('%PathToCoreWebclientModule%/js/utils/Types.js'),
		UrlUtils = require('%PathToCoreWebclientModule%/js/utils/Url.js'),

		Ajax = require('%PathToCoreWebclientModule%/js/Ajax.js'),
		App = require('%PathToCoreWebclientModule%/js/App.js'),

		WindowOpener = require('%PathToCoreWebclientModule%/js/WindowOpener.js'),

		CAbstractFileModel = require('%PathToCoreWebclientModule%/js/models/CAbstractFileModel.js')
	;

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
						oData = aParams[1]
					;
					if (oFile.hasAction('edit'))
					{
						oFile.removeAction('edit');
						if (oFile.oActionsData['edit'])
						{
							oFile.actions.unshift('edit');
							oFile.oActionsData['edit'].Text = TextUtils.i18n('%MODULENAME%/ACTION_EDIT_FILE');
							oFile.oActionsData['edit'].Handler = function () {
								var
									oWin = null,
									sUrl = UrlUtils.getAppPath() + this.getActionUrl('edit')
								;

								if (Types.isNonEmptyString(sUrl) && sUrl !== '#')
								{
									oWin = WindowOpener.open(sUrl, sUrl, false);

									if (oWin)
									{
										oWin.focus();
										var iInterval = setInterval(function () {
											if (oWin.closed)
											{
												oFile._editor_oMoment = moment();
												oFile._editor_setCheckChangesTimer();
												clearInterval(iInterval);
											}
										}, 500);
									}
								}
							}.bind(oFile);
							oFile._editor_setCheckChangesTimer = function () {
								clearTimeout(this._editor_iCheckChangesTimer);
								this._editor_iCheckChangesTimer = setTimeout(this._editor_checkChanges, 1000);
							}.bind(oFile);
							oFile._editor_checkChanges = function () {
								Ajax.send('Files', 'GetFileInfo', {
									'UserId': App.getUserId(),
									'Type': this.storageType(),
									'Path': this.path(),
									'Id': this.fileName()
								}, function (oResponse) {
									if (oResponse.Result.LastModified === this.iLastModified && moment().diff(oFile._editor_oMoment) < 20000)
									{
										oFile._editor_setCheckChangesTimer();
									}
									else
									{
										ModulesManager.run('FilesWebclient', 'refresh');
									}
								}, this);
							}.bind(oFile);
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
