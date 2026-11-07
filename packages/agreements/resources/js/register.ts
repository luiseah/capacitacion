import { registerMobilitySlot } from '@academic/extensions';
import { MobilityAgreementRow, MobilityAgreementsSection, PartnerAgreementsPicker } from '@agreements/components/mobility-agreements';

// Plugs the agreements into the pages of the mobilities. Import it once from the host's `app.tsx`.
registerMobilitySlot('create.partner', PartnerAgreementsPicker);
registerMobilitySlot('show.summary', MobilityAgreementRow);
registerMobilitySlot('show.section', MobilityAgreementsSection);
