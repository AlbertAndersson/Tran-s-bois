window.BOIS_CATALOG = Object.freeze({
  schemaVersion: 1,
  productionReady: false,
  verifiedTeamsAt: '2026-09-27',
  orderPeriod: Object.freeze({
    id: '2026-27-matchstall',
    label: 'Matchställ 2026/27',
    state: 'draft',
    closesAt: null
  }),
  teams: Object.freeze([
    { id: 'P9', label: 'P9', active: true },
    { id: 'F9', label: 'F9', active: true },
    { id: 'P13', label: 'P13', active: true },
    { id: 'F14', label: 'F14', active: true },
    { id: 'P16', label: 'P16', active: true },
    { id: 'Skridsko- & bandyskola 26/27', label: 'Skridsko- & bandyskola 26/27', active: true }
  ]),
  products: Object.freeze([
    Object.freeze({
      id: 'match-kit-knatte',
      label: 'Matchställ Knatte',
      description: 'Matchtröja och matchbyxa i Tranås BoIS färger.',
      supplierCode: '',
      priceStatus: 'example',
      components: Object.freeze({
        shirt: Object.freeze({
          label: 'Matchtröja',
          priceOre: 44900,
          sizes: Object.freeze(['128','140','152','164','XS','S'])
        }),
        shorts: Object.freeze({
          label: 'Matchbyxa',
          priceOre: 34900,
          sizes: Object.freeze(['128','140','152','164','XS','S'])
        })
      }),
      personalization: Object.freeze({
        name: Object.freeze({ label: 'Namntryck', priceOre: 10000, default: true }),
        number: Object.freeze({ label: 'Nummertryck', priceOre: 10000, default: true })
      })
    })
  ])
});