import Config from 'lib/Config';
import { joinUrlPaths } from 'lib/urls';
import { SECTION_NAME } from 'constants/section';

export const getSectionConfig = () => Config.getSection(SECTION_NAME);

/** Path of a client route, relative to the admin base URL that the router uses */
export const getClientPath = (...segments) => `/${joinUrlPaths(getSectionConfig().reactRoutePath, ...segments.map(String))}`;
