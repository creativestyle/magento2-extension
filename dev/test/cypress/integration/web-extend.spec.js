'use strict';

const merchantId = 'merchantId123';
const webTrackingSnippetUrl = Cypress.env('snippetUrl');
const predictUrl = `http://cdn.scarabresearch.com/js/${merchantId}/scarab-v2.js`;

const expectWebExtendFilesToBeIncluded = () => {
  cy.on('window:load', win => {
    const scripts = win.document.getElementsByTagName('script');
    if (scripts.length) {
      let jsFilesToBeIncluded = [predictUrl, webTrackingSnippetUrl];
      for (let i = 0; i < scripts.length; i++) {
        if (jsFilesToBeIncluded.includes(scripts[i].src)) {
          jsFilesToBeIncluded = jsFilesToBeIncluded.filter(e => e !== scripts[i].src);
        }
      }
      expect(jsFilesToBeIncluded.length).to.be.equal(0);
    }
  });
};

const addValidationForTrackingData = () => {
  cy.on('window:before:load', win => {
    win.Emarsys = win.Emarsys || {};
    win.Emarsys.Magento2 = win.Emarsys.Magento2 || {};
    win.Emarsys.Magento2.track = data => expectTrackDataToInclude(data);
  });
};

let expectedTrackDataList = [];
const expectTrackDataToInclude = data => {
  const expectedData = expectedTrackDataList.shift();
  if (expectedData) {
    expect(data).to.containSubset(expectedData);
  }
};

const clearTrackDataToInclude = () => expectedTrackDataList = [];

const loginWithTrackDataExpectation = customer => {
  expectedTrackDataList.push({
    search: false,
    category: false,
    product: false,
    exchangeRate: 1,
    slug: 'cypress-testslug',
    store: { merchantId }
  });
  expectedTrackDataList.push({
    search: false,
    category: false,
    product: false,
    exchangeRate: 1,
    slug: 'cypress-testslug',
    store: { merchantId }
  });

  cy.loginWithCustomer({ customer });

  cy.wait(2000);
};

const visitMainPage = () => {
  expectedTrackDataList.push({
    search: false,
    category: false,
    product: false,
    exchangeRate: 1,
    slug: 'cypress-testslug',
    store: { merchantId }
  });

  cy.visit('/');
  cy.wait(2000);
};

const searchForBag = () => {
  const searchTerm = 'bag';
  expectedTrackDataList.push({
    search: {
      term: searchTerm
    },
    category: false,
    product: false,
    exchangeRate: 1,
    slug: 'cypress-testslug',
    store: { merchantId }
  });

  cy.get('#search').type(searchTerm + '{enter}');
  cy.wait(2000);
};

const viewGearCategory = () => {
  expectedTrackDataList.push({
    search: false,
    category: {
      ids: ['3'],
      names: ['Gear']
    },
    product: false,
    exchangeRate: 1,
    slug: 'cypress-testslug',
    store: { merchantId }
  });

  cy.get('#ui-id-6').click();
  cy.wait(2000);
};

const viewAndAddFirstItemToCart = () => {
  expectedTrackDataList.push({
    category: {
      ids: ['3'],
      names: ['Gear']
    },
    exchangeRate: 1,
    product: { sku: '24-MB02', id: '6' },
    search: false,
    slug: 'cypress-testslug',
    store: { merchantId: 'merchantId123' }
  });

  cy.get('.product-items a[title="Fusion Backpack"]')
    .click({ force: true });
  cy.wait(2000);

  cy.get('#product-addtocart-button').click();
  cy.wait(10000);
  cy.get('#product-addtocart-button').click();
  cy.wait(2000);
};

const buyItem = () => {
  cy.get('.action.showcart').click();
  cy.wait(2000);
  cy.get('#top-cart-btn-checkout').click({ force: true });
  cy.wait(8000);

  cy.get('#checkout-step-shipping input.input-text[name="username"]').type('guest@cypress.net');
  cy.get('#checkout-step-shipping input.input-text[name="firstname"]').type('Guest');
  cy.get('#checkout-step-shipping input.input-text[name="lastname"]').type('Da Best');
  cy.get('#checkout-step-shipping input.input-text[name="street[0]"]').type('Cloverfield lane 1');
  cy.get('#checkout-step-shipping input.input-text[name="city"]').type('Nowhere');
  cy.get('#checkout-step-shipping select.select[name="country_id"]').select('HU');
  cy.get('#checkout-step-shipping input.input-text[name="postcode"]').type('2800');
  cy.get('#checkout-step-shipping input.input-text[name="telephone"]').type('0036905556969');

  cy.wait(2000);
  cy.get('button[data-role="opc-continue"]').click();
  cy.wait(2000);

  expectedTrackDataList.push({
    search: false,
    category: false,
    product: false,
    exchangeRate: 1,
    slug: 'cypress-testslug',
    store: { merchantId }
  });

  cy.get('button[title="Place Order"]').click();
  cy.wait(8000);

  cy.window().then(win => {
    const orderData = win.Emarsys.Magento2.orderData;
    expect(orderData.orderId).to.be.not.undefined;
    expect(orderData.items).to.be.eql([
      {
        item: '24-MB02',
        price: 118,
        quantity: 2
      }
    ]);
    expect(orderData.email).to.be.equal('guest@cypress.net');
    console.log('SUCCESSULLY BOUGHT');
  });
};

const buyItemWithLoggedInUser = customer => {
  cy.get('.action.showcart').click();
  cy.wait(2000);
  cy.get('#top-cart-btn-checkout').click({ force: true });
  cy.wait(8000);

  cy.get('#checkout-step-shipping input.input-text[name="street[0]"]').type('Cloverfield lane 1');
  cy.get('#checkout-step-shipping input.input-text[name="city"]').type('Nowhere');
  cy.get('#checkout-step-shipping select.select[name="country_id"]').select('HU');
  cy.get('#checkout-step-shipping input.input-text[name="postcode"]').type('2800');
  cy.get('#checkout-step-shipping input.input-text[name="telephone"]').type('0036905556969');

  cy.wait(2000);
  cy.get('button[data-role="opc-continue"]').click();
  cy.wait(2000);

  expectedTrackDataList.push({
    search: false,
    category: false,
    product: false,
    exchangeRate: 1,
    slug: 'cypress-testslug',
    store: { merchantId }
  });

  cy.get('button[title="Place Order"]').click();
  cy.wait(8000);

  cy.window().then(win => {
    const orderData = win.Emarsys.Magento2.orderData;
    console.log('orderData', JSON.stringify(orderData));
    console.log('customer', JSON.stringify(customer));
    expect(orderData.orderId).to.be.not.undefined;
    expect(orderData.items).to.be.eql([
      {
        item: '24-MB02',
        price: 118,
        quantity: 2
      }
    ]);
    expect(orderData.email).to.be.equal(customer.email);
  });
};

describe('Web extend scripts', function() {
  before(() => {
    cy.task('setConfig', {
      injectSnippet: 'enabled',
      merchantId,
      webTrackingSnippetUrl
    });
    cy.task('flushMagentoCache');
    cy.wait(1000);
  });

  beforeEach(() => {
    cy.wait(1000);
    cy.task('getDefaultCustomer').as('defaultCustomer');
    cy.task('getMagentoVersion').as('magentoVersion');
    clearTrackDataToInclude();
  });

  it('should send extended customer data', function() {
    addValidationForTrackingData();

    cy.loginWithCustomer({ customer: this.defaultCustomer }).then(() => {
      const customerData = JSON.parse(localStorage.getItem('mage-cache-storage'));
      expect(customerData.customer).to.be.not.undefined;
      expect(customerData.customer.id).to.be.not.undefined;
      expect(customerData.customer.email).to.be.not.undefined;
    });
    cy.task('clearEvents');
  });

  it('should include proper web tracking data', function() {
    expectWebExtendFilesToBeIncluded();

    addValidationForTrackingData();

    if (this.magentoVersion === '2.3.0') {
      loginWithTrackDataExpectation(this.defaultCustomer);
    }

    visitMainPage();

    searchForBag();

    viewGearCategory();

    viewAndAddFirstItemToCart();

    if (this.magentoVersion === '2.3.0') {
      buyItemWithLoggedInUser(this.defaultCustomer);
    } else {
      buyItem();
    }
    cy.task('clearEvents');
  });
});
