'use strict';

module.exports = function (oAppData) {
	var
		_ = require('underscore'),
		$ = require('jquery'),

		TextUtils = require('%PathToCoreWebclientModule%/js/utils/Text.js'),

		App = require('%PathToCoreWebclientModule%/js/App.js'),

		CAbstractFileModel = require('%PathToCoreWebclientModule%/js/models/CAbstractFileModel.js'),

		FilesActions = require('modules/%ModuleName%/js/utils/FilesActions.js')
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
						oRawData = aParams[1]
					;

					if (oFile.hasAction('view') && oFile.oActionsData['view'] && -1 !== $.inArray(oFile.extension(), aExtensionsToView))
					{
						delete oFile.oActionsData['view'].HandlerName;
						oFile.oActionsData['view'].Handler = FilesActions.view.bind(oFile);
					}
					if (oFile.hasAction('convert')) {
						oFile.removeAction('convert');
						if (oFile.oActionsData['convert']) {
							oFile.actions.unshift('convert');
							oFile.oActionsData['convert'].Text = TextUtils.i18n('%MODULENAME%/ACTION_EDIT_FILE');
							oFile.oActionsData['convert'].Handler = FilesActions.convert.bind(oFile);
						}
						if (oFile.hasAction('view'))
						{
							oFile.removeAction('view');
							oFile.actions.push('view');
						}
					}
					if (oFile.hasAction('edit'))
					{
						oFile.removeAction('edit');
						if (oFile.oActionsData['edit'])
						{
							oFile.actions.unshift('edit');
							oFile.oActionsData['edit'].Text = TextUtils.i18n('%MODULENAME%/ACTION_EDIT_FILE');
							oFile.oActionsData['edit'].Handler = FilesActions.edit.bind(oFile);
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
