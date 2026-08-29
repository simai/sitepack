const crypto = require('crypto');

const ALLOWED_OPERATIONS = new Set([
  'entity.map',
  'field.copy',
  'field.rename',
  'field.cast',
  'value.default',
  'enum.map',
  'relation.map',
  'asset.bind',
  'content.convert',
  'fallback.wrap',
  'value.drop'
]);

function canonicalize(value) {
  if (Array.isArray(value)) {
    return value.map(canonicalize);
  }
  if (value && typeof value === 'object') {
    return Object.keys(value).sort().reduce((result, key) => {
      result[key] = canonicalize(value[key]);
      return result;
    }, {});
  }
  return value;
}

function canonicalJson(value) {
  return JSON.stringify(canonicalize(value));
}

function digest(value) {
  return `sha256:${crypto.createHash('sha256').update(canonicalJson(value), 'utf8').digest('hex')}`;
}

function versionMatches(version, constraint) {
  if (constraint === '*' || constraint === version) {
    return true;
  }
  const match = /^(\d+)\.x$/.exec(constraint || '');
  return Boolean(match && version.startsWith(`${match[1]}.`));
}

function endpointSubject(endpoint) {
  if (!endpoint || typeof endpoint !== 'object') {
    return '';
  }
  if (endpoint.field) return `${endpoint.entity}.field.${endpoint.field}`;
  if (endpoint.relation) return `${endpoint.entity}.relation.${endpoint.relation}`;
  if (endpoint.asset) return `${endpoint.entity}.asset.${endpoint.asset}`;
  return endpoint.entity || '';
}

function capabilitySupports(capabilities, endpoint) {
  const entity = capabilities?.mapping?.entities?.[endpoint.entity];
  if (!entity) return false;
  if (endpoint.field) return Array.isArray(entity.fields) && entity.fields.includes(endpoint.field);
  if (endpoint.relation) return Array.isArray(entity.relations) && entity.relations.includes(endpoint.relation);
  if (endpoint.asset) return Array.isArray(entity.assets) && entity.assets.includes(endpoint.asset);
  return true;
}

function targetEndpointFor(subjectEndpoint, entityRule) {
  if (!entityRule?.to) return subjectEndpoint;
  return { ...subjectEndpoint, entity: entityRule.to.entity };
}

function profileSupported(capabilities, target) {
  const profiles = capabilities?.mapping?.semanticProfiles || [];
  return profiles.some((profile) => profile.id === target.profile
    && (!target.digest || profile.digest === target.digest)
    && (profile.versions || []).some((version) => versionMatches(target.version, version) || versionMatches(version, target.version)));
}

function compileMapping(profile, capabilities, recipe, inputs = []) {
  const findings = [];
  const summary = { exact: 0, transformed: 0, fallback: 0, skipped: 0, blocked: 0 };
  const missingRequired = [];
  const losses = new Set();
  const unsupportedExtensions = [];
  const rules = Array.isArray(recipe.rules) ? recipe.rules : [];
  const rulesBySubject = new Map(rules.map((rule) => [endpointSubject(rule.from), rule]));
  const supportedTransforms = new Set(capabilities?.mapping?.transforms || []);
  const supportedExtensions = new Set(capabilities?.mapping?.extensions || []);
  const supportedContentFormats = new Set(capabilities?.mapping?.contentFormats || []);
  const requiredExtensions = recipe?.requires?.extensions || [];
  const requiredTransforms = recipe?.requires?.transforms || [];
  const inventory = { entities: 0, fields: 0, relations: 0, assets: 0 };

  function add(category, subject, message, rule = null, loss = null) {
    summary[category] += 1;
    const finding = { category, subject, message };
    if (rule?.id) finding.rule = rule.id;
    if (loss) {
      finding.loss = loss;
      losses.add(loss);
    }
    findings.push(finding);
  }

  if (profile.id !== recipe.source.profile || !versionMatches(profile.version, recipe.source.version)) {
    add('blocked', 'source.profile', 'Source semantic profile does not satisfy the recipe constraint.');
    missingRequired.push(`${recipe.source.profile}@${recipe.source.version}`);
  }
  if (recipe.source.digest && digest(profile) !== recipe.source.digest) {
    add('blocked', 'source.profile.digest', 'Source semantic profile digest does not satisfy the recipe constraint.');
    missingRequired.push(`${recipe.source.profile}@${recipe.source.version}#${recipe.source.digest}`);
  }
  if (!profileSupported(capabilities, recipe.target)) {
    add('blocked', 'target.profile', 'Target adapter does not declare the recipe target semantic profile.');
    missingRequired.push(`${recipe.target.profile}@${recipe.target.version}`);
  }
  if (recipe.target.adapter && capabilities?.tool?.name !== recipe.target.adapter) {
    add('blocked', 'target.adapter', 'Target adapter identity does not satisfy the recipe constraint.');
    missingRequired.push(`adapter.${recipe.target.adapter}`);
  }
  for (const extension of requiredExtensions) {
    if (!supportedExtensions.has(extension)) {
      unsupportedExtensions.push(extension);
      add('blocked', `extension.${extension}`, 'Required extension is not supported by the target adapter.');
    }
  }
  for (const transform of requiredTransforms) {
    if (!supportedTransforms.has(transform)) {
      add('blocked', `transform.${transform}`, 'Required transform is not supported by the target adapter.');
      missingRequired.push(`transform.${transform}`);
    }
  }

  for (const [entityId, entity] of Object.entries(profile.entities || {})) {
    inventory.entities += 1;
    inventory.fields += Object.keys(entity.fields || {}).length;
    inventory.relations += Object.keys(entity.relations || {}).length;
    inventory.assets += Object.keys(entity.assets || {}).length;
    const entityRule = rulesBySubject.get(entityId);
    const targetEntity = targetEndpointFor({ entity: entityId }, entityRule);
    evaluate({ entity: entityId }, entityRule, Boolean(Object.values(entity.fields || {}).some((field) => field.required)
      || Object.values(entity.relations || {}).some((relation) => relation.required)
      || Object.values(entity.assets || {}).some((asset) => asset.required)), targetEntity);

    for (const [fieldId, field] of Object.entries(entity.fields || {})) {
      const endpoint = { entity: entityId, field: fieldId };
      const rule = rulesBySubject.get(endpointSubject(endpoint));
      if (field.format && !supportedContentFormats.has(field.format)) {
        const targetFormat = rule?.kind === 'content.convert' ? rule?.params?.format : null;
        if (!targetFormat || !supportedContentFormats.has(targetFormat)) {
          const required = Boolean(field.required) || Boolean(rule && rule.required !== false);
          add(required ? 'blocked' : 'skipped', endpointSubject(endpoint),
            'The target adapter does not support the field content format.', rule || null, rule?.loss || null);
          if (required) missingRequired.push(endpointSubject(endpoint));
          continue;
        }
      }
      evaluate(endpoint, rule, Boolean(field.required), targetEndpointFor(endpoint, entityRule));
    }
    for (const [relationId, relation] of Object.entries(entity.relations || {})) {
      const endpoint = { entity: entityId, relation: relationId };
      evaluate(endpoint, rulesBySubject.get(endpointSubject(endpoint)), Boolean(relation.required), targetEndpointFor(endpoint, entityRule));
    }
    for (const [assetId, asset] of Object.entries(entity.assets || {})) {
      const endpoint = { entity: entityId, asset: assetId };
      evaluate(endpoint, rulesBySubject.get(endpointSubject(endpoint)), Boolean(asset.required), targetEndpointFor(endpoint, entityRule));
    }
  }

  function evaluate(sourceEndpoint, rule, required, inheritedTarget) {
    const subject = endpointSubject(sourceEndpoint);
    if (!rule && capabilitySupports(capabilities, inheritedTarget)) {
      add(inheritedTarget.entity === sourceEndpoint.entity ? 'exact' : 'transformed', subject,
        inheritedTarget.entity === sourceEndpoint.entity ? 'Supported without transformation.' : 'Supported through the entity mapping.', entityRuleFor(sourceEndpoint.entity));
      return;
    }
    if (!rule) {
      const category = required ? 'blocked' : 'skipped';
      add(category, subject, required ? 'Required subject has no supported target mapping.' : 'Optional subject is unsupported.');
      if (required) missingRequired.push(subject);
      return;
    }
    const ruleRequired = rule.required !== false;
    if (!ALLOWED_OPERATIONS.has(rule.kind)) {
      add(ruleRequired ? 'blocked' : 'skipped', subject,
        ruleRequired ? 'Unknown required mapping operation.' : 'Unknown optional mapping operation was skipped.', rule, rule.loss || null);
      if (ruleRequired) missingRequired.push(subject);
      return;
    }
    if (rule.kind === 'content.convert' && !supportedTransforms.has(rule.converter)) {
      add(ruleRequired ? 'blocked' : 'skipped', subject,
        'The target adapter does not provide the requested content converter.', rule, rule.loss || null);
      if (ruleRequired) missingRequired.push(subject);
      return;
    }
    if (rule.kind === 'value.drop') {
      add(ruleRequired ? 'blocked' : 'skipped', subject,
        ruleRequired ? 'A required rule cannot drop data.' : 'The recipe explicitly drops this optional subject.', rule, rule.loss || 'content');
      if (ruleRequired) missingRequired.push(subject);
      return;
    }
    if (!capabilitySupports(capabilities, rule.to || inheritedTarget)) {
      add(ruleRequired || required ? 'blocked' : 'skipped', subject, 'The mapped target subject is unsupported.', rule, rule.loss || null);
      if (ruleRequired || required) missingRequired.push(subject);
      return;
    }
    add(rule.kind === 'fallback.wrap' ? 'fallback' : 'transformed', subject,
      rule.kind === 'fallback.wrap' ? 'The recipe preserves this subject through a fallback.' : 'The recipe transforms this subject.', rule, rule.loss || 'none');
  }

  function entityRuleFor(entityId) {
    const rule = rulesBySubject.get(entityId);
    return rule?.kind === 'entity.map' ? rule : null;
  }

  const forbiddenLosses = new Set(recipe?.lossPolicy?.failOn || []);
  for (const loss of losses) {
    if (forbiddenLosses.has(loss)) {
      add('blocked', `loss.${loss}`, 'The recipe loss policy forbids this loss level.', null, loss);
    }
  }

  const inputsDigest = digest({ profile, capabilities, recipe, artifacts: inputs });
  const planSteps = rules.filter((rule) => ALLOWED_OPERATIONS.has(rule.kind)
    && rule.kind !== 'value.drop'
    && (rule.kind !== 'content.convert' || supportedTransforms.has(rule.converter)));
  const plan = {
    version: '1.0.0',
    recipe: { id: recipe.id, version: recipe.version },
    source: { id: profile.id, version: profile.version },
    target: { id: recipe.target.profile, version: recipe.target.version },
    inputsDigest,
    inputs,
    expectedOutputs: summary,
    lossPolicy: recipe.lossPolicy || { allowed: ['none'], failOn: ['security'] },
    steps: planSteps
  };
  plan.digest = digest(plan);

  const status = summary.blocked > 0 ? 'incompatible'
    : (summary.fallback > 0 || summary.skipped > 0 ? 'partial' : 'compatible');
  const report = {
    spec: { name: 'sitepack-compatibility-report', version: '1.0.0' },
    status,
    source: { id: profile.id, version: profile.version },
    target: { id: capabilities.tool.name, version: capabilities.tool.version },
    recipe: { id: recipe.id, version: recipe.version },
    inputsDigest,
    planDigest: plan.digest,
    inventory,
    summary,
    losses: Array.from(losses).sort(),
    missingRequired: Array.from(new Set(missingRequired)).sort(),
    unsupportedExtensions: Array.from(new Set(unsupportedExtensions)).sort(),
    findings
  };

  return { plan, report };
}

module.exports = {
  ALLOWED_OPERATIONS,
  canonicalize,
  canonicalJson,
  digest,
  compileMapping
};
