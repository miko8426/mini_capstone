Continue the Eco Metrics project.

Develop the appliance usage monitoring and electricity calculation engine.

Implement server-side PHP calculations.

DAILY ELECTRICITY CONSUMPTION:

(Quantity × Wattage × Daily Operating Hours) / 1000

ELECTRICITY COST:

Total kWh × Applicable Electricity Rate

CARBON EMISSIONS:

Total kWh × Applicable Emission Factor

TASKS:

1. Create appliance usage input forms.
2. Record operating hours.
3. Record usage frequency.
4. Calculate daily electricity consumption.
5. Calculate weekly consumption.
6. Calculate monthly consumption based on the actual reporting period.
7. Calculate electricity expenses.
8. Calculate carbon emissions.
9. Display calculated results.
10. Allow authorized users to view usage history.

IMPORTANT:
- PHP must perform the official calculations.
- JavaScript may only provide calculation previews.
- Do not use voltage in the wattage-based kWh formula.
- Validate all input values.
- Prevent negative values and invalid operating hours.

TESTING:
Use this example:

Quantity = 5
Wattage = 250W
Operating hours = 8

Expected daily consumption = 10 kWh.

Verify the calculation using actual PHP execution.

Stop after completing the calculation engine.