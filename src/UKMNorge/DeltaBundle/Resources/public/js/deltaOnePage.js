class DeltaOnePage extends UKMOnePage {

    /**
     * Represents the Delta functionality for one page.
     * @constructor
     * @param {string} ajaxUrl - ajax main url e.g api here -> ...ukm.no/api/getSomething...
     * @param {EventElement []} eventElements - Event elements that contains information about the event and steps afterwards
     */
    constructor(ajaxUrl, eventElements) {
        super(ajaxUrl, eventElements);
    }
}