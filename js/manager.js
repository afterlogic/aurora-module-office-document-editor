'use strict';

module.exports = function (oAppData) {
	var
		_ = require('underscore'),

		TextUtils = require('%PathToCoreWebclientModule%/js/utils/Text.js'),

		App = require('%PathToCoreWebclientModule%/js/App.js'),

		CAbstractFileModel = require('%PathToCoreWebclientModule%/js/models/CAbstractFileModel.js')
	;

	if (App.isUserNormalOrTenant())
	{
		return {
			start: function () {
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
							oFile.oActionsData['edit'].HandlerName = 'viewFile';
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
