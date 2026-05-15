/**
 * nebulaCustomerAddresses — customer edit addresses tab.
 */

import { createModal } from '../modal';
import type { NebulaModelStore } from '../models';
import type { FieldOption, ModalState, SerializedPair } from '../types';

interface CustomerAddress {
    id?: string | number | null;
    firstname?: string;
    lastname?: string;
    company?: string;
    telephone?: string;
    fax?: string;
    street?: string | string[];
    city?: string;
    region?: string;
    region_id?: string | number;
    postcode?: string;
    country_id?: string;
}

interface CustomerAddressesConfig {
    existingAddresses?: CustomerAddress[];
    defaultBilling?: string | number | null;
    defaultShipping?: string | number | null;
    countries?: FieldOption[];
    regionsByCountry?: Record<string, FieldOption[]>;
}

interface AddressesState extends ModalState {
    addresses: CustomerAddress[];
    defaultBilling: string | number | null;
    defaultShipping: string | number | null;
    countries: FieldOption[];
    regionsByCountry: Record<string, FieldOption[]>;
    editIndex: number | null;
    formFirstname: string;
    formLastname: string;
    formCompany: string;
    formTelephone: string;
    formFax: string;
    formStreet0: string;
    formStreet1: string;
    formCity: string;
    formRegion: string;
    formRegionId: string;
    formPostcode: string;
    formCountryId: string;
    formDefaultBilling: boolean;
    formDefaultShipping: boolean;
    readonly currentRegions: FieldOption[];
    readonly hasRegions: boolean;
    readonly canSave: boolean;
    init(): void;
    destroy(): void;
    resetForm(): void;
    openEditor(editIdx?: number): void;
    saveAddress(): void;
    removeAddress(idx: number): void;
    setDefaultBilling(idx: number): void;
    setDefaultShipping(idx: number): void;
    isDefaultBilling(idx: number): boolean;
    isDefaultShipping(idx: number): boolean;
    formatStreet(addr: CustomerAddress): string;
    countryLabel(countryId: string): string;
    serialize(): SerializedPair[];
}

export function createCustomerAddresses(config: CustomerAddressesConfig): AddressesState {
    const modalMixin = createModal({
        size: 'lg',
        steps: [],
        onClose(this: ModalState): void {
            const self = this as unknown as AddressesState;
            self.resetForm();
            self.editIndex = null;
        },
    });

    const state: AddressesState = {
        ...modalMixin,
        addresses: config.existingAddresses ?? [],
        defaultBilling: config.defaultBilling ?? null,
        defaultShipping: config.defaultShipping ?? null,
        countries: config.countries ?? [],
        regionsByCountry: config.regionsByCountry ?? {},
        editIndex: null,

        formFirstname: '',
        formLastname: '',
        formCompany: '',
        formTelephone: '',
        formFax: '',
        formStreet0: '',
        formStreet1: '',
        formCity: '',
        formRegion: '',
        formRegionId: '',
        formPostcode: '',
        formCountryId: '',
        formDefaultBilling: false,
        formDefaultShipping: false,

        get currentRegions(): FieldOption[] {
            return this.regionsByCountry[this.formCountryId] ?? [];
        },

        get hasRegions(): boolean {
            return this.currentRegions.length > 0;
        },

        init(this: AddressesState): void {
            const models = window.Alpine.store('nebulaModels') as NebulaModelStore | undefined;
            if (models) models.register('section:addresses', this);
        },

        destroy(): void {
            const models = window.Alpine.store('nebulaModels') as NebulaModelStore | undefined;
            if (models) models.unregister('section:addresses');
        },

        resetForm(this: AddressesState): void {
            this.formFirstname = '';
            this.formLastname = '';
            this.formCompany = '';
            this.formTelephone = '';
            this.formFax = '';
            this.formStreet0 = '';
            this.formStreet1 = '';
            this.formCity = '';
            this.formRegion = '';
            this.formRegionId = '';
            this.formPostcode = '';
            this.formCountryId = '';
            this.formDefaultBilling = false;
            this.formDefaultShipping = false;
        },

        openEditor(this: AddressesState, editIdx?: number): void {
            this.editIndex = editIdx !== undefined ? editIdx : null;
            if (this.editIndex !== null) {
                const addr = this.addresses[this.editIndex];
                if (!addr) {
                    this.resetForm();
                    this.openModal();
                    return;
                }
                this.formFirstname = addr.firstname ?? '';
                this.formLastname = addr.lastname ?? '';
                this.formCompany = addr.company ?? '';
                this.formTelephone = addr.telephone ?? '';
                this.formFax = addr.fax ?? '';
                let street: string[] = [];
                if (Array.isArray(addr.street)) street = addr.street;
                else if (typeof addr.street === 'string') street = addr.street.split('\n');
                this.formStreet0 = street[0] ?? '';
                this.formStreet1 = street[1] ?? '';
                this.formCity = addr.city ?? '';
                this.formRegion = addr.region ?? '';
                this.formRegionId = addr.region_id ? String(addr.region_id) : '';
                this.formPostcode = addr.postcode ?? '';
                this.formCountryId = addr.country_id ?? '';
                const addrKey = addr.id ?? this.editIndex;
                this.formDefaultBilling =
                    this.defaultBilling !== null && String(this.defaultBilling) === String(addrKey);
                this.formDefaultShipping =
                    this.defaultShipping !== null && String(this.defaultShipping) === String(addrKey);
            } else {
                this.resetForm();
            }
            this.openModal();
        },

        get canSave(): boolean {
            if (
                !this.formFirstname.trim() ||
                !this.formLastname.trim() ||
                !this.formStreet0.trim() ||
                !this.formCity.trim() ||
                !this.formCountryId ||
                !this.formTelephone.trim()
            ) {
                return false;
            }
            if (this.hasRegions && !this.formRegionId) {
                return false;
            }
            return true;
        },

        saveAddress(this: AddressesState): void {
            const addr: CustomerAddress = {
                id: this.editIndex !== null ? this.addresses[this.editIndex]?.id ?? null : null,
                firstname: this.formFirstname,
                lastname: this.formLastname,
                company: this.formCompany,
                telephone: this.formTelephone,
                fax: this.formFax,
                street: [this.formStreet0, this.formStreet1],
                city: this.formCity,
                region: this.formRegion,
                region_id: this.formRegionId,
                postcode: this.formPostcode,
                country_id: this.formCountryId,
            };

            if (this.editIndex !== null) {
                this.addresses[this.editIndex] = addr;
            } else {
                this.addresses.push(addr);
            }

            const addrKey = (addr.id ??
                (this.editIndex !== null ? this.editIndex : this.addresses.length - 1)) as
                | string
                | number;

            if (this.formDefaultBilling) {
                this.defaultBilling = addrKey;
            } else if (String(this.defaultBilling) === String(addrKey)) {
                this.defaultBilling = null;
            }
            if (this.formDefaultShipping) {
                this.defaultShipping = addrKey;
            } else if (String(this.defaultShipping) === String(addrKey)) {
                this.defaultShipping = null;
            }

            this.closeModal();
        },

        removeAddress(this: AddressesState, idx: number): void {
            const addr = this.addresses[idx];
            if (!addr) return;
            const addrKey = addr.id ?? idx;
            if (String(this.defaultBilling) === String(addrKey)) this.defaultBilling = null;
            if (String(this.defaultShipping) === String(addrKey)) this.defaultShipping = null;
            this.addresses.splice(idx, 1);
        },

        setDefaultBilling(this: AddressesState, idx: number): void {
            const addr = this.addresses[idx];
            if (!addr) return;
            this.defaultBilling = addr.id ?? idx;
        },

        setDefaultShipping(this: AddressesState, idx: number): void {
            const addr = this.addresses[idx];
            if (!addr) return;
            this.defaultShipping = addr.id ?? idx;
        },

        isDefaultBilling(this: AddressesState, idx: number): boolean {
            const addr = this.addresses[idx];
            if (!addr) return false;
            const addrKey = addr.id ?? idx;
            return this.defaultBilling !== null && String(this.defaultBilling) === String(addrKey);
        },

        isDefaultShipping(this: AddressesState, idx: number): boolean {
            const addr = this.addresses[idx];
            if (!addr) return false;
            const addrKey = addr.id ?? idx;
            return this.defaultShipping !== null && String(this.defaultShipping) === String(addrKey);
        },

        formatStreet(_addr: CustomerAddress): string {
            const street = _addr.street ?? [];
            if (typeof street === 'string') return street;
            return street.filter((s) => s).join(', ');
        },

        countryLabel(this: AddressesState, countryId: string): string {
            const found = this.countries.find((c) => c.value === countryId);
            return found ? found.label : countryId;
        },

        serialize(this: AddressesState): SerializedPair[] {
            const pairs: SerializedPair[] = [];
            for (let i = 0; i < this.addresses.length; i++) {
                const addr = this.addresses[i];
                if (!addr) continue;
                const prefix = 'customer[address][' + String(i) + ']';
                const fields: Array<keyof CustomerAddress> = [
                    'firstname',
                    'lastname',
                    'company',
                    'city',
                    'region',
                    'region_id',
                    'postcode',
                    'country_id',
                    'telephone',
                    'fax',
                ];
                for (const key of fields) {
                    const raw = addr[key];
                    const value =
                        raw === undefined || raw === null
                            ? ''
                            : typeof raw === 'string' || typeof raw === 'number' || typeof raw === 'boolean'
                                ? raw
                                : String(raw);
                    pairs.push({ name: prefix + '[' + key + ']', value });
                }
                let street: string[] = [];
                if (Array.isArray(addr.street)) street = addr.street;
                else if (typeof addr.street === 'string') street = addr.street.split('\n');
                for (let si = 0; si < street.length; si++) {
                    pairs.push({ name: prefix + '[street][]', value: street[si] ?? '' });
                }
                if (addr.id !== undefined && addr.id !== null) {
                    pairs.push({ name: prefix + '[id]', value: addr.id });
                }
            }
            if (this.defaultBilling !== null) {
                pairs.push({ name: 'customer[default_billing]', value: this.defaultBilling });
            }
            if (this.defaultShipping !== null) {
                pairs.push({ name: 'customer[default_shipping]', value: this.defaultShipping });
            }
            return pairs;
        },
    };

    return state;
}

function register(): void {
    window.Alpine.data('nebulaCustomerAddresses', (config: CustomerAddressesConfig) =>
        createCustomerAddresses(config),
    );
}

if (window.Alpine) {
    register();
} else {
    document.addEventListener('alpine:init', register);
}
