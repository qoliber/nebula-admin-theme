"use strict";
(() => {
  // node_modules/alpinejs/dist/module.esm.js
  var flushPending = false;
  var flushing = false;
  var queue = [];
  var lastFlushedIndex = -1;
  var transactionActive = false;
  function scheduler(callback) {
    queueJob(callback);
  }
  function startTransaction() {
    transactionActive = true;
  }
  function commitTransaction() {
    transactionActive = false;
    queueFlush();
  }
  function queueJob(job) {
    if (!queue.includes(job))
      queue.push(job);
    queueFlush();
  }
  function dequeueJob(job) {
    let index = queue.indexOf(job);
    if (index !== -1 && index > lastFlushedIndex)
      queue.splice(index, 1);
  }
  function queueFlush() {
    if (!flushing && !flushPending) {
      if (transactionActive)
        return;
      flushPending = true;
      queueMicrotask(flushJobs);
    }
  }
  function flushJobs() {
    flushPending = false;
    flushing = true;
    for (let i = 0; i < queue.length; i++) {
      queue[i]();
      lastFlushedIndex = i;
    }
    queue.length = 0;
    lastFlushedIndex = -1;
    flushing = false;
  }
  var reactive;
  var effect;
  var release;
  var raw;
  var shouldSchedule = true;
  function disableEffectScheduling(callback) {
    shouldSchedule = false;
    callback();
    shouldSchedule = true;
  }
  function setReactivityEngine(engine) {
    reactive = engine.reactive;
    release = engine.release;
    effect = (callback) => engine.effect(callback, { scheduler: (task) => {
      if (shouldSchedule) {
        scheduler(task);
      } else {
        task();
      }
    } });
    raw = engine.raw;
  }
  function overrideEffect(override) {
    effect = override;
  }
  function elementBoundEffect(el) {
    let cleanup2 = () => {
    };
    let wrappedEffect = (callback) => {
      let effectReference = effect(callback);
      if (!el._x_effects) {
        el._x_effects = /* @__PURE__ */ new Set();
        el._x_runEffects = () => {
          el._x_effects.forEach((i) => i());
        };
      }
      el._x_effects.add(effectReference);
      cleanup2 = () => {
        if (effectReference === void 0)
          return;
        el._x_effects.delete(effectReference);
        release(effectReference);
      };
      return effectReference;
    };
    return [wrappedEffect, () => {
      cleanup2();
    }];
  }
  function watch(getter, callback) {
    let firstTime = true;
    let oldValue;
    let oldValueJSON;
    let effectReference = effect(() => {
      let value = getter();
      let newJSON = JSON.stringify(value);
      if (!firstTime) {
        if (typeof value === "object" || value !== oldValue) {
          let previousValue = typeof oldValue === "object" ? JSON.parse(oldValueJSON) : oldValue;
          queueMicrotask(() => {
            callback(value, previousValue);
          });
        }
      }
      oldValue = value;
      oldValueJSON = newJSON;
      firstTime = false;
    });
    return () => release(effectReference);
  }
  async function transaction(callback) {
    startTransaction();
    try {
      await callback();
      await Promise.resolve();
    } finally {
      commitTransaction();
    }
  }
  var onAttributeAddeds = [];
  var onElRemoveds = [];
  var onElAddeds = [];
  function onElAdded(callback) {
    onElAddeds.push(callback);
  }
  function onElRemoved(el, callback) {
    if (typeof callback === "function") {
      if (!el._x_cleanups)
        el._x_cleanups = [];
      el._x_cleanups.push(callback);
    } else {
      callback = el;
      onElRemoveds.push(callback);
    }
  }
  function onAttributesAdded(callback) {
    onAttributeAddeds.push(callback);
  }
  function onAttributeRemoved(el, name, callback) {
    if (!el._x_attributeCleanups)
      el._x_attributeCleanups = {};
    if (!el._x_attributeCleanups[name])
      el._x_attributeCleanups[name] = [];
    el._x_attributeCleanups[name].push(callback);
  }
  function cleanupAttributes(el, names) {
    if (!el._x_attributeCleanups)
      return;
    Object.entries(el._x_attributeCleanups).forEach(([name, value]) => {
      if (names === void 0 || names.includes(name)) {
        value.forEach((i) => i());
        delete el._x_attributeCleanups[name];
      }
    });
  }
  function cleanupElement(el) {
    el._x_effects?.forEach(dequeueJob);
    while (el._x_cleanups?.length)
      el._x_cleanups.pop()();
  }
  var observer = new MutationObserver(onMutate);
  var currentlyObserving = false;
  function startObservingMutations() {
    observer.observe(document, { subtree: true, childList: true, attributes: true, attributeOldValue: true });
    currentlyObserving = true;
  }
  function stopObservingMutations() {
    flushObserver();
    observer.disconnect();
    currentlyObserving = false;
  }
  var queuedMutations = [];
  function flushObserver() {
    let records = observer.takeRecords();
    queuedMutations.push(() => records.length > 0 && onMutate(records));
    let queueLengthWhenTriggered = queuedMutations.length;
    queueMicrotask(() => {
      if (queuedMutations.length === queueLengthWhenTriggered) {
        while (queuedMutations.length > 0)
          queuedMutations.shift()();
      }
    });
  }
  function mutateDom(callback) {
    if (!currentlyObserving)
      return callback();
    stopObservingMutations();
    let result = callback();
    startObservingMutations();
    return result;
  }
  var isCollecting = false;
  var deferredMutations = [];
  function deferMutations() {
    isCollecting = true;
  }
  function flushAndStopDeferringMutations() {
    isCollecting = false;
    onMutate(deferredMutations);
    deferredMutations = [];
  }
  function onMutate(mutations) {
    if (isCollecting) {
      deferredMutations = deferredMutations.concat(mutations);
      return;
    }
    let addedNodes = [];
    let removedNodes = /* @__PURE__ */ new Set();
    let addedAttributes = /* @__PURE__ */ new Map();
    let removedAttributes = /* @__PURE__ */ new Map();
    for (let i = 0; i < mutations.length; i++) {
      if (mutations[i].target._x_ignoreMutationObserver)
        continue;
      if (mutations[i].type === "childList") {
        mutations[i].removedNodes.forEach((node) => {
          if (node.nodeType !== 1)
            return;
          if (!node._x_marker)
            return;
          removedNodes.add(node);
        });
        mutations[i].addedNodes.forEach((node) => {
          if (node.nodeType !== 1)
            return;
          if (removedNodes.has(node)) {
            removedNodes.delete(node);
            return;
          }
          if (node._x_marker)
            return;
          addedNodes.push(node);
        });
      }
      if (mutations[i].type === "attributes") {
        let el = mutations[i].target;
        let name = mutations[i].attributeName;
        let oldValue = mutations[i].oldValue;
        let add2 = () => {
          if (!addedAttributes.has(el))
            addedAttributes.set(el, []);
          addedAttributes.get(el).push({ name, value: el.getAttribute(name) });
        };
        let remove = () => {
          if (!removedAttributes.has(el))
            removedAttributes.set(el, []);
          removedAttributes.get(el).push(name);
        };
        if (el.hasAttribute(name) && oldValue === null) {
          add2();
        } else if (el.hasAttribute(name)) {
          remove();
          add2();
        } else {
          remove();
        }
      }
    }
    removedAttributes.forEach((attrs, el) => {
      cleanupAttributes(el, attrs);
    });
    addedAttributes.forEach((attrs, el) => {
      onAttributeAddeds.forEach((i) => i(el, attrs));
    });
    for (let node of removedNodes) {
      if (addedNodes.some((i) => i.contains(node)))
        continue;
      onElRemoveds.forEach((i) => i(node));
    }
    for (let node of addedNodes) {
      if (!node.isConnected)
        continue;
      onElAddeds.forEach((i) => i(node));
    }
    addedNodes = null;
    removedNodes = null;
    addedAttributes = null;
    removedAttributes = null;
  }
  function scope(node) {
    return mergeProxies(closestDataStack(node));
  }
  function addScopeToNode(node, data2, referenceNode) {
    node._x_dataStack = [data2, ...closestDataStack(referenceNode || node)];
    return () => {
      node._x_dataStack = node._x_dataStack.filter((i) => i !== data2);
    };
  }
  function closestDataStack(node) {
    if (node._x_dataStack)
      return node._x_dataStack;
    if (typeof ShadowRoot === "function" && node instanceof ShadowRoot) {
      return closestDataStack(node.host);
    }
    if (!node.parentNode) {
      return [];
    }
    return closestDataStack(node.parentNode);
  }
  function mergeProxies(objects) {
    return new Proxy({ objects }, mergeProxyTrap);
  }
  function keyInPrototypeChain(obj, key) {
    if (obj === null || obj === Object.prototype)
      return null;
    if (Object.prototype.hasOwnProperty.call(obj, key))
      return obj;
    return keyInPrototypeChain(Object.getPrototypeOf(obj), key);
  }
  var mergeProxyTrap = {
    ownKeys({ objects }) {
      return Array.from(
        new Set(objects.flatMap((i) => Object.keys(i)))
      );
    },
    has({ objects }, name) {
      if (name == Symbol.unscopables)
        return false;
      return objects.some(
        (obj) => Object.prototype.hasOwnProperty.call(obj, name) || Reflect.has(obj, name)
      );
    },
    get({ objects }, name, thisProxy) {
      if (name == "toJSON")
        return collapseProxies;
      return Reflect.get(
        objects.find(
          (obj) => Reflect.has(obj, name)
        ) || {},
        name,
        thisProxy
      );
    },
    set({ objects }, name, value, thisProxy) {
      let target;
      for (const obj of objects) {
        target = keyInPrototypeChain(obj, name);
        if (target)
          break;
      }
      if (!target)
        target = objects[objects.length - 1];
      const descriptor = Object.getOwnPropertyDescriptor(target, name);
      if (descriptor?.set && descriptor?.get)
        return descriptor.set.call(thisProxy, value) || true;
      return Reflect.set(target, name, value);
    }
  };
  function collapseProxies() {
    let keys = Reflect.ownKeys(this);
    return keys.reduce((acc, key) => {
      acc[key] = Reflect.get(this, key);
      return acc;
    }, {});
  }
  function initInterceptors(data2) {
    let isObject3 = (val) => typeof val === "object" && !Array.isArray(val) && val !== null;
    let recurse = (obj, basePath = "") => {
      Object.entries(Object.getOwnPropertyDescriptors(obj)).forEach(([key, { value, enumerable }]) => {
        if (enumerable === false || value === void 0)
          return;
        if (typeof value === "object" && value !== null && value.__v_skip)
          return;
        let path = basePath === "" ? key : `${basePath}.${key}`;
        if (typeof value === "object" && value !== null && value._x_interceptor) {
          obj[key] = value.initialize(data2, path, key);
        } else {
          if (isObject3(value) && value !== obj && !(value instanceof Element)) {
            recurse(value, path);
          }
        }
      });
    };
    return recurse(data2);
  }
  function interceptor(callback, mutateObj = () => {
  }) {
    let obj = {
      initialValue: void 0,
      _x_interceptor: true,
      initialize(data2, path, key) {
        return callback(this.initialValue, () => get(data2, path), (value) => set(data2, path, value), path, key);
      }
    };
    mutateObj(obj);
    return (initialValue) => {
      if (typeof initialValue === "object" && initialValue !== null && initialValue._x_interceptor) {
        let initialize = obj.initialize.bind(obj);
        obj.initialize = (data2, path, key) => {
          let innerValue = initialValue.initialize(data2, path, key);
          obj.initialValue = innerValue;
          return initialize(data2, path, key);
        };
      } else {
        obj.initialValue = initialValue;
      }
      return obj;
    };
  }
  function get(obj, path) {
    return path.split(".").reduce((carry, segment) => carry[segment], obj);
  }
  function set(obj, path, value) {
    if (typeof path === "string")
      path = path.split(".");
    if (path.length === 1)
      obj[path[0]] = value;
    else if (path.length === 0)
      throw error;
    else {
      if (obj[path[0]])
        return set(obj[path[0]], path.slice(1), value);
      else {
        obj[path[0]] = {};
        return set(obj[path[0]], path.slice(1), value);
      }
    }
  }
  var magics = {};
  function magic(name, callback) {
    magics[name] = callback;
  }
  function injectMagics(obj, el) {
    let memoizedUtilities = getUtilities(el);
    Object.entries(magics).forEach(([name, callback]) => {
      Object.defineProperty(obj, `$${name}`, {
        get() {
          return callback(el, memoizedUtilities);
        },
        enumerable: false
      });
    });
    return obj;
  }
  function getUtilities(el) {
    let [utilities, cleanup2] = getElementBoundUtilities(el);
    let utils = { interceptor, ...utilities };
    onElRemoved(el, cleanup2);
    return utils;
  }
  function tryCatch(el, expression, callback, ...args) {
    try {
      return callback(...args);
    } catch (e) {
      handleError(e, el, expression);
    }
  }
  function handleError(...args) {
    return errorHandler(...args);
  }
  var errorHandler = normalErrorHandler;
  function setErrorHandler(handler4) {
    errorHandler = handler4;
  }
  function normalErrorHandler(error2, el, expression = void 0) {
    error2 = Object.assign(
      error2 ?? { message: "No error message given." },
      { el, expression }
    );
    console.warn(`Alpine Expression Error: ${error2.message}

${expression ? 'Expression: "' + expression + '"\n\n' : ""}`, el);
    setTimeout(() => {
      throw error2;
    }, 0);
  }
  var shouldAutoEvaluateFunctions = true;
  function dontAutoEvaluateFunctions(callback) {
    let cache = shouldAutoEvaluateFunctions;
    shouldAutoEvaluateFunctions = false;
    let result = callback();
    shouldAutoEvaluateFunctions = cache;
    return result;
  }
  function evaluate(el, expression, extras = {}) {
    let result;
    evaluateLater(el, expression)((value) => result = value, extras);
    return result;
  }
  function evaluateLater(...args) {
    return theEvaluatorFunction(...args);
  }
  var theEvaluatorFunction = () => {
  };
  function setEvaluator(newEvaluator) {
    theEvaluatorFunction = newEvaluator;
  }
  var theRawEvaluatorFunction;
  function setRawEvaluator(newEvaluator) {
    theRawEvaluatorFunction = newEvaluator;
  }
  function normalEvaluator(el, expression) {
    let overriddenMagics = {};
    injectMagics(overriddenMagics, el);
    let dataStack = [overriddenMagics, ...closestDataStack(el)];
    let evaluator = typeof expression === "function" ? generateEvaluatorFromFunction(dataStack, expression) : generateEvaluatorFromString(dataStack, expression, el);
    return tryCatch.bind(null, el, expression, evaluator);
  }
  function generateEvaluatorFromFunction(dataStack, func) {
    return (receiver = () => {
    }, { scope: scope2 = {}, params = [], context } = {}) => {
      if (!shouldAutoEvaluateFunctions) {
        runIfTypeOfFunction(receiver, func, mergeProxies([scope2, ...dataStack]), params);
        return;
      }
      let result = func.apply(mergeProxies([scope2, ...dataStack]), params);
      runIfTypeOfFunction(receiver, result);
    };
  }
  var evaluatorMemo = {};
  function generateFunctionFromString(expression, el) {
    if (evaluatorMemo[expression]) {
      return evaluatorMemo[expression];
    }
    let AsyncFunction = Object.getPrototypeOf(async function() {
    }).constructor;
    let rightSideSafeExpression = /^[\n\s]*if.*\(.*\)/.test(expression.trim()) || /^(let|const)\s/.test(expression.trim()) ? `(async()=>{ ${expression} })()` : expression;
    const safeAsyncFunction = () => {
      try {
        let func2 = new AsyncFunction(
          ["__self", "scope"],
          `with (scope) { __self.result = ${rightSideSafeExpression} }; __self.finished = true; return __self.result;`
        );
        Object.defineProperty(func2, "name", {
          value: `[Alpine] ${expression}`
        });
        return func2;
      } catch (error2) {
        handleError(error2, el, expression);
        return Promise.resolve();
      }
    };
    let func = safeAsyncFunction();
    evaluatorMemo[expression] = func;
    return func;
  }
  function generateEvaluatorFromString(dataStack, expression, el) {
    let func = generateFunctionFromString(expression, el);
    return (receiver = () => {
    }, { scope: scope2 = {}, params = [], context } = {}) => {
      func.result = void 0;
      func.finished = false;
      let completeScope = mergeProxies([scope2, ...dataStack]);
      if (typeof func === "function") {
        let promise = func.call(context, func, completeScope).catch((error2) => handleError(error2, el, expression));
        if (func.finished) {
          runIfTypeOfFunction(receiver, func.result, completeScope, params, el);
          func.result = void 0;
        } else {
          promise.then((result) => {
            runIfTypeOfFunction(receiver, result, completeScope, params, el);
          }).catch((error2) => handleError(error2, el, expression)).finally(() => func.result = void 0);
        }
      }
    };
  }
  function runIfTypeOfFunction(receiver, value, scope2, params, el) {
    if (shouldAutoEvaluateFunctions && typeof value === "function") {
      let result = value.apply(scope2, params);
      if (result instanceof Promise) {
        result.then((i) => runIfTypeOfFunction(receiver, i, scope2, params)).catch((error2) => handleError(error2, el, value));
      } else {
        receiver(result);
      }
    } else if (typeof value === "object" && value instanceof Promise) {
      value.then((i) => receiver(i));
    } else {
      receiver(value);
    }
  }
  function evaluateRaw(...args) {
    return theRawEvaluatorFunction(...args);
  }
  function normalRawEvaluator(el, expression, extras = {}) {
    let overriddenMagics = {};
    injectMagics(overriddenMagics, el);
    let dataStack = [overriddenMagics, ...closestDataStack(el)];
    let scope2 = mergeProxies([extras.scope ?? {}, ...dataStack]);
    let params = extras.params ?? [];
    if (expression.includes("await")) {
      let AsyncFunction = Object.getPrototypeOf(async function() {
      }).constructor;
      let rightSideSafeExpression = /^[\n\s]*if.*\(.*\)/.test(expression.trim()) || /^(let|const)\s/.test(expression.trim()) ? `(async()=>{ ${expression} })()` : expression;
      let func = new AsyncFunction(
        ["scope"],
        `with (scope) { let __result = ${rightSideSafeExpression}; return __result }`
      );
      let result = func.call(extras.context, scope2);
      return result;
    } else {
      let rightSideSafeExpression = /^[\n\s]*if.*\(.*\)/.test(expression.trim()) || /^(let|const)\s/.test(expression.trim()) ? `(()=>{ ${expression} })()` : expression;
      let func = new Function(
        ["scope"],
        `with (scope) { let __result = ${rightSideSafeExpression}; return __result }`
      );
      let result = func.call(extras.context, scope2);
      if (typeof result === "function" && shouldAutoEvaluateFunctions) {
        return result.apply(scope2, params);
      }
      return result;
    }
  }
  var prefixAsString = "x-";
  function prefix(subject = "") {
    return prefixAsString + subject;
  }
  function setPrefix(newPrefix) {
    prefixAsString = newPrefix;
  }
  var directiveHandlers = {};
  function directive(name, callback) {
    directiveHandlers[name] = callback;
    return {
      before(directive2) {
        if (!directiveHandlers[directive2]) {
          console.warn(String.raw`Cannot find directive \`${directive2}\`. \`${name}\` will use the default order of execution`);
          return;
        }
        const pos = directiveOrder.indexOf(directive2);
        directiveOrder.splice(pos >= 0 ? pos : directiveOrder.indexOf("DEFAULT"), 0, name);
      }
    };
  }
  function directiveExists(name) {
    return Object.keys(directiveHandlers).includes(name);
  }
  function directives(el, attributes, originalAttributeOverride) {
    attributes = Array.from(attributes);
    if (el._x_virtualDirectives) {
      let vAttributes = Object.entries(el._x_virtualDirectives).map(([name, value]) => ({ name, value }));
      let staticAttributes = attributesOnly(vAttributes);
      vAttributes = vAttributes.map((attribute) => {
        if (staticAttributes.find((attr) => attr.name === attribute.name)) {
          return {
            name: `x-bind:${attribute.name}`,
            value: `"${attribute.value}"`
          };
        }
        return attribute;
      });
      attributes = attributes.concat(vAttributes);
    }
    let transformedAttributeMap = {};
    let directives2 = attributes.map(toTransformedAttributes((newName, oldName) => transformedAttributeMap[newName] = oldName)).filter(outNonAlpineAttributes).map(toParsedDirectives(transformedAttributeMap, originalAttributeOverride)).sort(byPriority);
    return directives2.map((directive2) => {
      return getDirectiveHandler(el, directive2);
    });
  }
  function attributesOnly(attributes) {
    return Array.from(attributes).map(toTransformedAttributes()).filter((attr) => !outNonAlpineAttributes(attr));
  }
  var isDeferringHandlers = false;
  var directiveHandlerStacks = /* @__PURE__ */ new Map();
  var currentHandlerStackKey = Symbol();
  function deferHandlingDirectives(callback) {
    isDeferringHandlers = true;
    let key = Symbol();
    currentHandlerStackKey = key;
    directiveHandlerStacks.set(key, []);
    let flushHandlers = () => {
      while (directiveHandlerStacks.get(key).length)
        directiveHandlerStacks.get(key).shift()();
      directiveHandlerStacks.delete(key);
    };
    let stopDeferring = () => {
      isDeferringHandlers = false;
      flushHandlers();
    };
    callback(flushHandlers);
    stopDeferring();
  }
  function getElementBoundUtilities(el) {
    let cleanups = [];
    let cleanup2 = (callback) => cleanups.push(callback);
    let [effect3, cleanupEffect] = elementBoundEffect(el);
    cleanups.push(cleanupEffect);
    let utilities = {
      Alpine: alpine_default,
      effect: effect3,
      cleanup: cleanup2,
      evaluateLater: evaluateLater.bind(evaluateLater, el),
      evaluate: evaluate.bind(evaluate, el)
    };
    let doCleanup = () => cleanups.forEach((i) => i());
    return [utilities, doCleanup];
  }
  function getDirectiveHandler(el, directive2) {
    let noop = () => {
    };
    let handler4 = directiveHandlers[directive2.type] || noop;
    let [utilities, cleanup2] = getElementBoundUtilities(el);
    onAttributeRemoved(el, directive2.original, cleanup2);
    let fullHandler = () => {
      if (el._x_ignore || el._x_ignoreSelf)
        return;
      handler4.inline && handler4.inline(el, directive2, utilities);
      handler4 = handler4.bind(handler4, el, directive2, utilities);
      isDeferringHandlers ? directiveHandlerStacks.get(currentHandlerStackKey).push(handler4) : handler4();
    };
    fullHandler.runCleanups = cleanup2;
    return fullHandler;
  }
  var startingWith = (subject, replacement) => ({ name, value }) => {
    if (name.startsWith(subject))
      name = name.replace(subject, replacement);
    return { name, value };
  };
  var into = (i) => i;
  function toTransformedAttributes(callback = () => {
  }) {
    return ({ name, value }) => {
      let { name: newName, value: newValue } = attributeTransformers.reduce((carry, transform) => {
        return transform(carry);
      }, { name, value });
      if (newName !== name)
        callback(newName, name);
      return { name: newName, value: newValue };
    };
  }
  var attributeTransformers = [];
  function mapAttributes(callback) {
    attributeTransformers.push(callback);
  }
  function outNonAlpineAttributes({ name }) {
    return alpineAttributeRegex().test(name);
  }
  var alpineAttributeRegex = () => new RegExp(`^${prefixAsString}([^:^.]+)\\b`);
  function toParsedDirectives(transformedAttributeMap, originalAttributeOverride) {
    return ({ name, value }) => {
      if (name === value)
        value = "";
      let typeMatch = name.match(alpineAttributeRegex());
      let valueMatch = name.match(/:([a-zA-Z0-9\-_:]+)/);
      let modifiers = name.match(/\.[^.\]]+(?=[^\]]*$)/g) || [];
      let original = originalAttributeOverride || transformedAttributeMap[name] || name;
      return {
        type: typeMatch ? typeMatch[1] : null,
        value: valueMatch ? valueMatch[1] : null,
        modifiers: modifiers.map((i) => i.replace(".", "")),
        expression: value,
        original
      };
    };
  }
  var DEFAULT = "DEFAULT";
  var directiveOrder = [
    "ignore",
    "ref",
    "data",
    "id",
    "anchor",
    "bind",
    "init",
    "for",
    "model",
    "modelable",
    "transition",
    "show",
    "if",
    DEFAULT,
    "teleport"
  ];
  function byPriority(a, b) {
    let typeA = directiveOrder.indexOf(a.type) === -1 ? DEFAULT : a.type;
    let typeB = directiveOrder.indexOf(b.type) === -1 ? DEFAULT : b.type;
    return directiveOrder.indexOf(typeA) - directiveOrder.indexOf(typeB);
  }
  function dispatch(el, name, detail = {}, options = {}) {
    return el.dispatchEvent(
      new CustomEvent(name, {
        detail,
        bubbles: true,
        // Allows events to pass the shadow DOM barrier.
        composed: true,
        cancelable: true,
        // Allows overriding the default event options.
        ...options
      })
    );
  }
  function walk(el, callback) {
    if (typeof ShadowRoot === "function" && el instanceof ShadowRoot) {
      Array.from(el.children).forEach((el2) => walk(el2, callback));
      return;
    }
    let skip = false;
    callback(el, () => skip = true);
    if (skip)
      return;
    let node = el.firstElementChild;
    while (node) {
      walk(node, callback, false);
      node = node.nextElementSibling;
    }
  }
  function warn(message, ...args) {
    console.warn(`Alpine Warning: ${message}`, ...args);
  }
  var started = false;
  function start() {
    if (started)
      warn("Alpine has already been initialized on this page. Calling Alpine.start() more than once can cause problems.");
    started = true;
    if (!document.body)
      warn("Unable to initialize. Trying to load Alpine before `<body>` is available. Did you forget to add `defer` in Alpine's `<script>` tag?");
    dispatch(document, "alpine:init");
    dispatch(document, "alpine:initializing");
    startObservingMutations();
    onElAdded((el) => initTree(el, walk));
    onElRemoved((el) => destroyTree(el));
    onAttributesAdded((el, attrs) => {
      directives(el, attrs).forEach((handle) => handle());
    });
    let outNestedComponents = (el) => !closestRoot(el.parentElement, true);
    Array.from(document.querySelectorAll(allSelectors().join(","))).filter(outNestedComponents).forEach((el) => {
      initTree(el);
    });
    dispatch(document, "alpine:initialized");
    setTimeout(() => {
      warnAboutMissingPlugins();
    });
  }
  var rootSelectorCallbacks = [];
  var initSelectorCallbacks = [];
  function rootSelectors() {
    return rootSelectorCallbacks.map((fn) => fn());
  }
  function allSelectors() {
    return rootSelectorCallbacks.concat(initSelectorCallbacks).map((fn) => fn());
  }
  function addRootSelector(selectorCallback) {
    rootSelectorCallbacks.push(selectorCallback);
  }
  function addInitSelector(selectorCallback) {
    initSelectorCallbacks.push(selectorCallback);
  }
  function closestRoot(el, includeInitSelectors = false) {
    return findClosest(el, (element) => {
      const selectors = includeInitSelectors ? allSelectors() : rootSelectors();
      if (selectors.some((selector) => element.matches(selector)))
        return true;
    });
  }
  function findClosest(el, callback) {
    if (!el)
      return;
    if (callback(el))
      return el;
    if (el._x_teleportBack)
      return findClosest(el._x_teleportBack, callback);
    if (el.parentNode instanceof ShadowRoot) {
      return findClosest(el.parentNode.host, callback);
    }
    if (!el.parentElement)
      return;
    return findClosest(el.parentElement, callback);
  }
  function isRoot(el) {
    return rootSelectors().some((selector) => el.matches(selector));
  }
  var initInterceptors2 = [];
  function interceptInit(callback) {
    initInterceptors2.push(callback);
  }
  var markerDispenser = 1;
  function initTree(el, walker = walk, intercept = () => {
  }) {
    if (findClosest(el, (i) => i._x_ignore))
      return;
    deferHandlingDirectives(() => {
      walker(el, (el2, skip) => {
        if (el2._x_marker)
          return;
        intercept(el2, skip);
        initInterceptors2.forEach((i) => i(el2, skip));
        directives(el2, el2.attributes).forEach((handle) => handle());
        if (!el2._x_ignore)
          el2._x_marker = markerDispenser++;
        el2._x_ignore && skip();
      });
    });
  }
  function destroyTree(root, walker = walk) {
    walker(root, (el) => {
      cleanupElement(el);
      cleanupAttributes(el);
      delete el._x_marker;
    });
  }
  function warnAboutMissingPlugins() {
    let pluginDirectives = [
      ["ui", "dialog", ["[x-dialog], [x-popover]"]],
      ["anchor", "anchor", ["[x-anchor]"]],
      ["sort", "sort", ["[x-sort]"]]
    ];
    pluginDirectives.forEach(([plugin2, directive2, selectors]) => {
      if (directiveExists(directive2))
        return;
      selectors.some((selector) => {
        if (document.querySelector(selector)) {
          warn(`found "${selector}", but missing ${plugin2} plugin`);
          return true;
        }
      });
    });
  }
  var tickStack = [];
  var isHolding = false;
  function nextTick(callback = () => {
  }) {
    queueMicrotask(() => {
      isHolding || setTimeout(() => {
        releaseNextTicks();
      });
    });
    return new Promise((res) => {
      tickStack.push(() => {
        callback();
        res();
      });
    });
  }
  function releaseNextTicks() {
    isHolding = false;
    while (tickStack.length)
      tickStack.shift()();
  }
  function holdNextTicks() {
    isHolding = true;
  }
  function setClasses(el, value) {
    if (Array.isArray(value)) {
      return setClassesFromString(el, value.join(" "));
    } else if (typeof value === "object" && value !== null) {
      return setClassesFromObject(el, value);
    } else if (typeof value === "function") {
      return setClasses(el, value());
    }
    return setClassesFromString(el, value);
  }
  function splitClasses(classString) {
    return classString.split(/\s/).filter(Boolean);
  }
  function setClassesFromString(el, classString) {
    let missingClasses = (classString2) => splitClasses(classString2).filter((i) => !el.classList.contains(i)).filter(Boolean);
    let addClassesAndReturnUndo = (classes) => {
      el.classList.add(...classes);
      return () => {
        el.classList.remove(...classes);
      };
    };
    classString = classString === true ? classString = "" : classString || "";
    return addClassesAndReturnUndo(missingClasses(classString));
  }
  function setClassesFromObject(el, classObject) {
    let forAdd = Object.entries(classObject).flatMap(([classString, bool]) => bool ? splitClasses(classString) : false).filter(Boolean);
    let forRemove = Object.entries(classObject).flatMap(([classString, bool]) => !bool ? splitClasses(classString) : false).filter(Boolean);
    let added = [];
    let removed = [];
    forRemove.forEach((i) => {
      if (el.classList.contains(i)) {
        el.classList.remove(i);
        removed.push(i);
      }
    });
    forAdd.forEach((i) => {
      if (!el.classList.contains(i)) {
        el.classList.add(i);
        added.push(i);
      }
    });
    return () => {
      removed.forEach((i) => el.classList.add(i));
      added.forEach((i) => el.classList.remove(i));
    };
  }
  function setStyles(el, value) {
    if (typeof value === "object" && value !== null) {
      return setStylesFromObject(el, value);
    }
    return setStylesFromString(el, value);
  }
  function setStylesFromObject(el, value) {
    let previousStyles = {};
    Object.entries(value).forEach(([key, value2]) => {
      previousStyles[key] = el.style[key];
      if (!key.startsWith("--")) {
        key = kebabCase(key);
      }
      el.style.setProperty(key, value2);
    });
    setTimeout(() => {
      if (el.style.length === 0) {
        el.removeAttribute("style");
      }
    });
    return () => {
      setStyles(el, previousStyles);
    };
  }
  function setStylesFromString(el, value) {
    let cache = el.getAttribute("style", value);
    el.setAttribute("style", value);
    return () => {
      el.setAttribute("style", cache || "");
    };
  }
  function kebabCase(subject) {
    return subject.replace(/([a-z])([A-Z])/g, "$1-$2").toLowerCase();
  }
  function once(callback, fallback = () => {
  }) {
    let called = false;
    return function() {
      if (!called) {
        called = true;
        callback.apply(this, arguments);
      } else {
        fallback.apply(this, arguments);
      }
    };
  }
  directive("transition", (el, { value, modifiers, expression }, { evaluate: evaluate2 }) => {
    if (typeof expression === "function")
      expression = evaluate2(expression);
    if (expression === false)
      return;
    if (!expression || typeof expression === "boolean") {
      registerTransitionsFromHelper(el, modifiers, value);
    } else {
      registerTransitionsFromClassString(el, expression, value);
    }
  });
  function registerTransitionsFromClassString(el, classString, stage) {
    registerTransitionObject(el, setClasses, "");
    let directiveStorageMap = {
      "enter": (classes) => {
        el._x_transition.enter.during = classes;
      },
      "enter-start": (classes) => {
        el._x_transition.enter.start = classes;
      },
      "enter-end": (classes) => {
        el._x_transition.enter.end = classes;
      },
      "leave": (classes) => {
        el._x_transition.leave.during = classes;
      },
      "leave-start": (classes) => {
        el._x_transition.leave.start = classes;
      },
      "leave-end": (classes) => {
        el._x_transition.leave.end = classes;
      }
    };
    directiveStorageMap[stage](classString);
  }
  function registerTransitionsFromHelper(el, modifiers, stage) {
    registerTransitionObject(el, setStyles);
    let doesntSpecify = !modifiers.includes("in") && !modifiers.includes("out") && !stage;
    let transitioningIn = doesntSpecify || modifiers.includes("in") || ["enter"].includes(stage);
    let transitioningOut = doesntSpecify || modifiers.includes("out") || ["leave"].includes(stage);
    if (modifiers.includes("in") && !doesntSpecify) {
      modifiers = modifiers.filter((i, index) => index < modifiers.indexOf("out"));
    }
    if (modifiers.includes("out") && !doesntSpecify) {
      modifiers = modifiers.filter((i, index) => index > modifiers.indexOf("out"));
    }
    let wantsAll = !modifiers.includes("opacity") && !modifiers.includes("scale");
    let wantsOpacity = wantsAll || modifiers.includes("opacity");
    let wantsScale = wantsAll || modifiers.includes("scale");
    let opacityValue = wantsOpacity ? 0 : 1;
    let scaleValue = wantsScale ? modifierValue(modifiers, "scale", 95) / 100 : 1;
    let delay = modifierValue(modifiers, "delay", 0) / 1e3;
    let origin = modifierValue(modifiers, "origin", "center");
    let property = "opacity, transform";
    let durationIn = modifierValue(modifiers, "duration", 150) / 1e3;
    let durationOut = modifierValue(modifiers, "duration", 75) / 1e3;
    let easing = `cubic-bezier(0.4, 0.0, 0.2, 1)`;
    if (transitioningIn) {
      el._x_transition.enter.during = {
        transformOrigin: origin,
        transitionDelay: `${delay}s`,
        transitionProperty: property,
        transitionDuration: `${durationIn}s`,
        transitionTimingFunction: easing
      };
      el._x_transition.enter.start = {
        opacity: opacityValue,
        transform: `scale(${scaleValue})`
      };
      el._x_transition.enter.end = {
        opacity: 1,
        transform: `scale(1)`
      };
    }
    if (transitioningOut) {
      el._x_transition.leave.during = {
        transformOrigin: origin,
        transitionDelay: `${delay}s`,
        transitionProperty: property,
        transitionDuration: `${durationOut}s`,
        transitionTimingFunction: easing
      };
      el._x_transition.leave.start = {
        opacity: 1,
        transform: `scale(1)`
      };
      el._x_transition.leave.end = {
        opacity: opacityValue,
        transform: `scale(${scaleValue})`
      };
    }
  }
  function registerTransitionObject(el, setFunction, defaultValue = {}) {
    if (!el._x_transition)
      el._x_transition = {
        enter: { during: defaultValue, start: defaultValue, end: defaultValue },
        leave: { during: defaultValue, start: defaultValue, end: defaultValue },
        in(before = () => {
        }, after = () => {
        }) {
          transition(el, setFunction, {
            during: this.enter.during,
            start: this.enter.start,
            end: this.enter.end
          }, before, after);
        },
        out(before = () => {
        }, after = () => {
        }) {
          transition(el, setFunction, {
            during: this.leave.during,
            start: this.leave.start,
            end: this.leave.end
          }, before, after);
        }
      };
  }
  window.Element.prototype._x_toggleAndCascadeWithTransitions = function(el, value, show, hide) {
    const nextTick2 = document.visibilityState === "visible" ? requestAnimationFrame : setTimeout;
    let clickAwayCompatibleShow = () => nextTick2(show);
    if (value) {
      if (el._x_transition && (el._x_transition.enter || el._x_transition.leave)) {
        el._x_transition.enter && (Object.entries(el._x_transition.enter.during).length || Object.entries(el._x_transition.enter.start).length || Object.entries(el._x_transition.enter.end).length) ? el._x_transition.in(show) : clickAwayCompatibleShow();
      } else {
        el._x_transition ? el._x_transition.in(show) : clickAwayCompatibleShow();
      }
      return;
    }
    el._x_hidePromise = el._x_transition ? new Promise((resolve, reject) => {
      el._x_transition.out(() => {
      }, () => resolve(hide));
      el._x_transitioning && el._x_transitioning.beforeCancel(() => reject({ isFromCancelledTransition: true }));
    }) : Promise.resolve(hide);
    queueMicrotask(() => {
      let closest = closestHide(el);
      if (closest) {
        if (!closest._x_hideChildren)
          closest._x_hideChildren = [];
        closest._x_hideChildren.push(el);
      } else {
        nextTick2(() => {
          let hideAfterChildren = (el2) => {
            let carry = Promise.all([
              el2._x_hidePromise,
              ...(el2._x_hideChildren || []).map(hideAfterChildren)
            ]).then(([i]) => i?.());
            delete el2._x_hidePromise;
            delete el2._x_hideChildren;
            return carry;
          };
          hideAfterChildren(el).catch((e) => {
            if (!e.isFromCancelledTransition)
              throw e;
          });
        });
      }
    });
  };
  function closestHide(el) {
    let parent = el.parentNode;
    if (!parent)
      return;
    return parent._x_hidePromise ? parent : closestHide(parent);
  }
  function transition(el, setFunction, { during, start: start2, end } = {}, before = () => {
  }, after = () => {
  }) {
    if (el._x_transitioning)
      el._x_transitioning.cancel();
    if (Object.keys(during).length === 0 && Object.keys(start2).length === 0 && Object.keys(end).length === 0) {
      before();
      after();
      return;
    }
    let undoStart, undoDuring, undoEnd;
    performTransition(el, {
      start() {
        undoStart = setFunction(el, start2);
      },
      during() {
        undoDuring = setFunction(el, during);
      },
      before,
      end() {
        undoStart();
        undoEnd = setFunction(el, end);
      },
      after,
      cleanup() {
        undoDuring();
        undoEnd();
      }
    });
  }
  function performTransition(el, stages) {
    let interrupted, reachedBefore, reachedEnd;
    let finish = once(() => {
      mutateDom(() => {
        interrupted = true;
        if (!reachedBefore)
          stages.before();
        if (!reachedEnd) {
          stages.end();
          releaseNextTicks();
        }
        stages.after();
        if (el.isConnected)
          stages.cleanup();
        delete el._x_transitioning;
      });
    });
    el._x_transitioning = {
      beforeCancels: [],
      beforeCancel(callback) {
        this.beforeCancels.push(callback);
      },
      cancel: once(function() {
        while (this.beforeCancels.length) {
          this.beforeCancels.shift()();
        }
        ;
        finish();
      }),
      finish
    };
    mutateDom(() => {
      stages.start();
      stages.during();
    });
    holdNextTicks();
    requestAnimationFrame(() => {
      if (interrupted)
        return;
      let duration = Number(getComputedStyle(el).transitionDuration.replace(/,.*/, "").replace("s", "")) * 1e3;
      let delay = Number(getComputedStyle(el).transitionDelay.replace(/,.*/, "").replace("s", "")) * 1e3;
      if (duration === 0)
        duration = Number(getComputedStyle(el).animationDuration.replace("s", "")) * 1e3;
      mutateDom(() => {
        stages.before();
      });
      reachedBefore = true;
      requestAnimationFrame(() => {
        if (interrupted)
          return;
        mutateDom(() => {
          stages.end();
        });
        releaseNextTicks();
        setTimeout(el._x_transitioning.finish, duration + delay);
        reachedEnd = true;
      });
    });
  }
  function modifierValue(modifiers, key, fallback) {
    if (modifiers.indexOf(key) === -1)
      return fallback;
    const rawValue = modifiers[modifiers.indexOf(key) + 1];
    if (!rawValue)
      return fallback;
    if (key === "scale") {
      if (isNaN(rawValue))
        return fallback;
    }
    if (key === "duration" || key === "delay") {
      let match = rawValue.match(/([0-9]+)ms/);
      if (match)
        return match[1];
    }
    if (key === "origin") {
      if (["top", "right", "left", "center", "bottom"].includes(modifiers[modifiers.indexOf(key) + 2])) {
        return [rawValue, modifiers[modifiers.indexOf(key) + 2]].join(" ");
      }
    }
    return rawValue;
  }
  var isCloning = false;
  function skipDuringClone(callback, fallback = () => {
  }) {
    return (...args) => isCloning ? fallback(...args) : callback(...args);
  }
  function onlyDuringClone(callback) {
    return (...args) => isCloning && callback(...args);
  }
  var interceptors = [];
  function interceptClone(callback) {
    interceptors.push(callback);
  }
  function cloneNode(from, to) {
    interceptors.forEach((i) => i(from, to));
    isCloning = true;
    dontRegisterReactiveSideEffects(() => {
      initTree(to, (el, callback) => {
        callback(el, () => {
        });
      });
    });
    isCloning = false;
  }
  var isCloningLegacy = false;
  function clone(oldEl, newEl) {
    if (!newEl._x_dataStack)
      newEl._x_dataStack = oldEl._x_dataStack;
    isCloning = true;
    isCloningLegacy = true;
    dontRegisterReactiveSideEffects(() => {
      cloneTree(newEl);
    });
    isCloning = false;
    isCloningLegacy = false;
  }
  function cloneTree(el) {
    let hasRunThroughFirstEl = false;
    let shallowWalker = (el2, callback) => {
      walk(el2, (el3, skip) => {
        if (hasRunThroughFirstEl && isRoot(el3))
          return skip();
        hasRunThroughFirstEl = true;
        callback(el3, skip);
      });
    };
    initTree(el, shallowWalker);
  }
  function dontRegisterReactiveSideEffects(callback) {
    let cache = effect;
    overrideEffect((callback2, el) => {
      let storedEffect = cache(callback2);
      release(storedEffect);
      return () => {
      };
    });
    callback();
    overrideEffect(cache);
  }
  function bind(el, name, value, modifiers = []) {
    if (!el._x_bindings)
      el._x_bindings = reactive({});
    el._x_bindings[name] = value;
    name = modifiers.includes("camel") ? camelCase(name) : name;
    switch (name) {
      case "value":
        bindInputValue(el, value);
        break;
      case "style":
        bindStyles(el, value);
        break;
      case "class":
        bindClasses(el, value);
        break;
      case "selected":
      case "checked":
        bindAttributeAndProperty(el, name, value);
        break;
      default:
        bindAttribute(el, name, value);
        break;
    }
  }
  function bindInputValue(el, value) {
    if (isRadio(el)) {
      if (el.attributes.value === void 0) {
        el.value = value;
      }
    } else if (isCheckbox(el)) {
      if (Number.isInteger(value)) {
        el.value = value;
      } else if (!Array.isArray(value) && typeof value !== "boolean" && ![null, void 0].includes(value)) {
        el.value = String(value);
      } else {
        if (Array.isArray(value)) {
          el.checked = value.some((val) => checkedAttrLooseCompare(val, el.value));
        } else {
          el.checked = !!value;
        }
      }
    } else if (el.tagName === "SELECT") {
      updateSelect(el, value);
    } else {
      if (el.value === value)
        return;
      el.value = value === void 0 ? "" : value;
    }
  }
  function bindClasses(el, value) {
    if (el._x_undoAddedClasses)
      el._x_undoAddedClasses();
    el._x_undoAddedClasses = setClasses(el, value);
  }
  function bindStyles(el, value) {
    if (el._x_undoAddedStyles)
      el._x_undoAddedStyles();
    el._x_undoAddedStyles = setStyles(el, value);
  }
  function bindAttributeAndProperty(el, name, value) {
    bindAttribute(el, name, value);
    setPropertyIfChanged(el, name, value);
  }
  function bindAttribute(el, name, value) {
    if ([null, void 0, false].includes(value) && attributeShouldntBePreservedIfFalsy(name)) {
      el.removeAttribute(name);
    } else {
      if (isBooleanAttr(name))
        value = name;
      setIfChanged(el, name, value);
    }
  }
  function setIfChanged(el, attrName, value) {
    if (el.getAttribute(attrName) != value) {
      el.setAttribute(attrName, value);
    }
  }
  function setPropertyIfChanged(el, propName, value) {
    if (el[propName] !== value) {
      el[propName] = value;
    }
  }
  function updateSelect(el, value) {
    const arrayWrappedValue = [].concat(value).map((value2) => {
      return value2 + "";
    });
    Array.from(el.options).forEach((option) => {
      option.selected = arrayWrappedValue.includes(option.value);
    });
  }
  function camelCase(subject) {
    return subject.toLowerCase().replace(/-(\w)/g, (match, char) => char.toUpperCase());
  }
  function checkedAttrLooseCompare(valueA, valueB) {
    return valueA == valueB;
  }
  function safeParseBoolean(rawValue) {
    if ([1, "1", "true", "on", "yes", true].includes(rawValue)) {
      return true;
    }
    if ([0, "0", "false", "off", "no", false].includes(rawValue)) {
      return false;
    }
    return rawValue ? Boolean(rawValue) : null;
  }
  var booleanAttributes = /* @__PURE__ */ new Set([
    "allowfullscreen",
    "async",
    "autofocus",
    "autoplay",
    "checked",
    "controls",
    "default",
    "defer",
    "disabled",
    "formnovalidate",
    "inert",
    "ismap",
    "itemscope",
    "loop",
    "multiple",
    "muted",
    "nomodule",
    "novalidate",
    "open",
    "playsinline",
    "readonly",
    "required",
    "reversed",
    "selected",
    "shadowrootclonable",
    "shadowrootdelegatesfocus",
    "shadowrootserializable"
  ]);
  function isBooleanAttr(attrName) {
    return booleanAttributes.has(attrName);
  }
  function attributeShouldntBePreservedIfFalsy(name) {
    return !["aria-pressed", "aria-checked", "aria-expanded", "aria-selected"].includes(name);
  }
  function getBinding(el, name, fallback) {
    if (el._x_bindings && el._x_bindings[name] !== void 0)
      return el._x_bindings[name];
    return getAttributeBinding(el, name, fallback);
  }
  function extractProp(el, name, fallback, extract = true) {
    if (el._x_bindings && el._x_bindings[name] !== void 0)
      return el._x_bindings[name];
    if (el._x_inlineBindings && el._x_inlineBindings[name] !== void 0) {
      let binding = el._x_inlineBindings[name];
      binding.extract = extract;
      return dontAutoEvaluateFunctions(() => {
        return evaluate(el, binding.expression);
      });
    }
    return getAttributeBinding(el, name, fallback);
  }
  function getAttributeBinding(el, name, fallback) {
    let attr = el.getAttribute(name);
    if (attr === null)
      return typeof fallback === "function" ? fallback() : fallback;
    if (attr === "")
      return true;
    if (isBooleanAttr(name)) {
      return !![name, "true"].includes(attr);
    }
    return attr;
  }
  function isCheckbox(el) {
    return el.type === "checkbox" || el.localName === "ui-checkbox" || el.localName === "ui-switch";
  }
  function isRadio(el) {
    return el.type === "radio" || el.localName === "ui-radio";
  }
  function debounce(func, wait) {
    let timeout;
    return function() {
      const context = this, args = arguments;
      const later = function() {
        timeout = null;
        func.apply(context, args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  }
  function throttle(func, limit) {
    let inThrottle;
    return function() {
      let context = this, args = arguments;
      if (!inThrottle) {
        func.apply(context, args);
        inThrottle = true;
        setTimeout(() => inThrottle = false, limit);
      }
    };
  }
  function entangle({ get: outerGet, set: outerSet }, { get: innerGet, set: innerSet }) {
    let firstRun = true;
    let outerHash;
    let innerHash;
    let reference = effect(() => {
      let outer = outerGet();
      let inner = innerGet();
      if (firstRun) {
        innerSet(cloneIfObject(outer));
        firstRun = false;
      } else {
        let outerHashLatest = JSON.stringify(outer);
        let innerHashLatest = JSON.stringify(inner);
        if (outerHashLatest !== outerHash) {
          innerSet(cloneIfObject(outer));
        } else if (outerHashLatest !== innerHashLatest) {
          outerSet(cloneIfObject(inner));
        } else {
        }
      }
      outerHash = JSON.stringify(outerGet());
      innerHash = JSON.stringify(innerGet());
    });
    return () => {
      release(reference);
    };
  }
  function cloneIfObject(value) {
    return typeof value === "object" ? JSON.parse(JSON.stringify(value)) : value;
  }
  function plugin(callback) {
    let callbacks = Array.isArray(callback) ? callback : [callback];
    callbacks.forEach((i) => i(alpine_default));
  }
  var stores = {};
  var isReactive = false;
  function store(name, value) {
    if (!isReactive) {
      stores = reactive(stores);
      isReactive = true;
    }
    if (value === void 0) {
      return stores[name];
    }
    stores[name] = value;
    initInterceptors(stores[name]);
    if (typeof value === "object" && value !== null && value.hasOwnProperty("init") && typeof value.init === "function") {
      stores[name].init();
    }
  }
  function getStores() {
    return stores;
  }
  var binds = {};
  function bind2(name, bindings) {
    let getBindings = typeof bindings !== "function" ? () => bindings : bindings;
    if (name instanceof Element) {
      return applyBindingsObject(name, getBindings());
    } else {
      binds[name] = getBindings;
    }
    return () => {
    };
  }
  function injectBindingProviders(obj) {
    Object.entries(binds).forEach(([name, callback]) => {
      Object.defineProperty(obj, name, {
        get() {
          return (...args) => {
            return callback(...args);
          };
        }
      });
    });
    return obj;
  }
  function applyBindingsObject(el, obj, original) {
    let cleanupRunners = [];
    while (cleanupRunners.length)
      cleanupRunners.pop()();
    let attributes = Object.entries(obj).map(([name, value]) => ({ name, value }));
    let staticAttributes = attributesOnly(attributes);
    attributes = attributes.map((attribute) => {
      if (staticAttributes.find((attr) => attr.name === attribute.name)) {
        return {
          name: `x-bind:${attribute.name}`,
          value: `"${attribute.value}"`
        };
      }
      return attribute;
    });
    directives(el, attributes, original).map((handle) => {
      cleanupRunners.push(handle.runCleanups);
      handle();
    });
    return () => {
      while (cleanupRunners.length)
        cleanupRunners.pop()();
    };
  }
  var datas = {};
  function data(name, callback) {
    datas[name] = callback;
  }
  function injectDataProviders(obj, context) {
    Object.entries(datas).forEach(([name, callback]) => {
      Object.defineProperty(obj, name, {
        get() {
          return (...args) => {
            return callback.bind(context)(...args);
          };
        },
        enumerable: false
      });
    });
    return obj;
  }
  var Alpine = {
    get reactive() {
      return reactive;
    },
    get release() {
      return release;
    },
    get effect() {
      return effect;
    },
    get raw() {
      return raw;
    },
    get transaction() {
      return transaction;
    },
    version: "3.15.11",
    flushAndStopDeferringMutations,
    dontAutoEvaluateFunctions,
    disableEffectScheduling,
    startObservingMutations,
    stopObservingMutations,
    setReactivityEngine,
    onAttributeRemoved,
    onAttributesAdded,
    closestDataStack,
    skipDuringClone,
    onlyDuringClone,
    addRootSelector,
    addInitSelector,
    setErrorHandler,
    interceptClone,
    addScopeToNode,
    deferMutations,
    mapAttributes,
    evaluateLater,
    interceptInit,
    initInterceptors,
    injectMagics,
    setEvaluator,
    setRawEvaluator,
    mergeProxies,
    extractProp,
    findClosest,
    onElRemoved,
    closestRoot,
    destroyTree,
    interceptor,
    // INTERNAL: not public API and is subject to change without major release.
    transition,
    // INTERNAL
    setStyles,
    // INTERNAL
    mutateDom,
    directive,
    entangle,
    throttle,
    debounce,
    evaluate,
    evaluateRaw,
    initTree,
    nextTick,
    prefixed: prefix,
    prefix: setPrefix,
    plugin,
    magic,
    store,
    start,
    clone,
    // INTERNAL
    cloneNode,
    // INTERNAL
    bound: getBinding,
    $data: scope,
    watch,
    walk,
    data,
    bind: bind2
  };
  var alpine_default = Alpine;
  function makeMap(str, expectsLowerCase) {
    const map = /* @__PURE__ */ Object.create(null);
    const list = str.split(",");
    for (let i = 0; i < list.length; i++) {
      map[list[i]] = true;
    }
    return expectsLowerCase ? (val) => !!map[val.toLowerCase()] : (val) => !!map[val];
  }
  var specialBooleanAttrs = `itemscope,allowfullscreen,formnovalidate,ismap,nomodule,novalidate,readonly`;
  var isBooleanAttr2 = /* @__PURE__ */ makeMap(specialBooleanAttrs + `,async,autofocus,autoplay,controls,default,defer,disabled,hidden,loop,open,required,reversed,scoped,seamless,checked,muted,multiple,selected`);
  var EMPTY_OBJ = true ? Object.freeze({}) : {};
  var EMPTY_ARR = true ? Object.freeze([]) : [];
  var hasOwnProperty = Object.prototype.hasOwnProperty;
  var hasOwn = (val, key) => hasOwnProperty.call(val, key);
  var isArray = Array.isArray;
  var isMap = (val) => toTypeString(val) === "[object Map]";
  var isString = (val) => typeof val === "string";
  var isSymbol = (val) => typeof val === "symbol";
  var isObject = (val) => val !== null && typeof val === "object";
  var objectToString = Object.prototype.toString;
  var toTypeString = (value) => objectToString.call(value);
  var toRawType = (value) => {
    return toTypeString(value).slice(8, -1);
  };
  var isIntegerKey = (key) => isString(key) && key !== "NaN" && key[0] !== "-" && "" + parseInt(key, 10) === key;
  var cacheStringFunction = (fn) => {
    const cache = /* @__PURE__ */ Object.create(null);
    return (str) => {
      const hit = cache[str];
      return hit || (cache[str] = fn(str));
    };
  };
  var camelizeRE = /-(\w)/g;
  var camelize = cacheStringFunction((str) => {
    return str.replace(camelizeRE, (_, c) => c ? c.toUpperCase() : "");
  });
  var hyphenateRE = /\B([A-Z])/g;
  var hyphenate = cacheStringFunction((str) => str.replace(hyphenateRE, "-$1").toLowerCase());
  var capitalize = cacheStringFunction((str) => str.charAt(0).toUpperCase() + str.slice(1));
  var toHandlerKey = cacheStringFunction((str) => str ? `on${capitalize(str)}` : ``);
  var hasChanged = (value, oldValue) => value !== oldValue && (value === value || oldValue === oldValue);
  var targetMap = /* @__PURE__ */ new WeakMap();
  var effectStack = [];
  var activeEffect;
  var ITERATE_KEY = Symbol(true ? "iterate" : "");
  var MAP_KEY_ITERATE_KEY = Symbol(true ? "Map key iterate" : "");
  function isEffect(fn) {
    return fn && fn._isEffect === true;
  }
  function effect2(fn, options = EMPTY_OBJ) {
    if (isEffect(fn)) {
      fn = fn.raw;
    }
    const effect3 = createReactiveEffect(fn, options);
    if (!options.lazy) {
      effect3();
    }
    return effect3;
  }
  function stop(effect3) {
    if (effect3.active) {
      cleanup(effect3);
      if (effect3.options.onStop) {
        effect3.options.onStop();
      }
      effect3.active = false;
    }
  }
  var uid = 0;
  function createReactiveEffect(fn, options) {
    const effect3 = function reactiveEffect() {
      if (!effect3.active) {
        return fn();
      }
      if (!effectStack.includes(effect3)) {
        cleanup(effect3);
        try {
          enableTracking();
          effectStack.push(effect3);
          activeEffect = effect3;
          return fn();
        } finally {
          effectStack.pop();
          resetTracking();
          activeEffect = effectStack[effectStack.length - 1];
        }
      }
    };
    effect3.id = uid++;
    effect3.allowRecurse = !!options.allowRecurse;
    effect3._isEffect = true;
    effect3.active = true;
    effect3.raw = fn;
    effect3.deps = [];
    effect3.options = options;
    return effect3;
  }
  function cleanup(effect3) {
    const { deps } = effect3;
    if (deps.length) {
      for (let i = 0; i < deps.length; i++) {
        deps[i].delete(effect3);
      }
      deps.length = 0;
    }
  }
  var shouldTrack = true;
  var trackStack = [];
  function pauseTracking() {
    trackStack.push(shouldTrack);
    shouldTrack = false;
  }
  function enableTracking() {
    trackStack.push(shouldTrack);
    shouldTrack = true;
  }
  function resetTracking() {
    const last = trackStack.pop();
    shouldTrack = last === void 0 ? true : last;
  }
  function track(target, type, key) {
    if (!shouldTrack || activeEffect === void 0) {
      return;
    }
    let depsMap = targetMap.get(target);
    if (!depsMap) {
      targetMap.set(target, depsMap = /* @__PURE__ */ new Map());
    }
    let dep = depsMap.get(key);
    if (!dep) {
      depsMap.set(key, dep = /* @__PURE__ */ new Set());
    }
    if (!dep.has(activeEffect)) {
      dep.add(activeEffect);
      activeEffect.deps.push(dep);
      if (activeEffect.options.onTrack) {
        activeEffect.options.onTrack({
          effect: activeEffect,
          target,
          type,
          key
        });
      }
    }
  }
  function trigger(target, type, key, newValue, oldValue, oldTarget) {
    const depsMap = targetMap.get(target);
    if (!depsMap) {
      return;
    }
    const effects = /* @__PURE__ */ new Set();
    const add2 = (effectsToAdd) => {
      if (effectsToAdd) {
        effectsToAdd.forEach((effect3) => {
          if (effect3 !== activeEffect || effect3.allowRecurse) {
            effects.add(effect3);
          }
        });
      }
    };
    if (type === "clear") {
      depsMap.forEach(add2);
    } else if (key === "length" && isArray(target)) {
      depsMap.forEach((dep, key2) => {
        if (key2 === "length" || key2 >= newValue) {
          add2(dep);
        }
      });
    } else {
      if (key !== void 0) {
        add2(depsMap.get(key));
      }
      switch (type) {
        case "add":
          if (!isArray(target)) {
            add2(depsMap.get(ITERATE_KEY));
            if (isMap(target)) {
              add2(depsMap.get(MAP_KEY_ITERATE_KEY));
            }
          } else if (isIntegerKey(key)) {
            add2(depsMap.get("length"));
          }
          break;
        case "delete":
          if (!isArray(target)) {
            add2(depsMap.get(ITERATE_KEY));
            if (isMap(target)) {
              add2(depsMap.get(MAP_KEY_ITERATE_KEY));
            }
          }
          break;
        case "set":
          if (isMap(target)) {
            add2(depsMap.get(ITERATE_KEY));
          }
          break;
      }
    }
    const run = (effect3) => {
      if (effect3.options.onTrigger) {
        effect3.options.onTrigger({
          effect: effect3,
          target,
          key,
          type,
          newValue,
          oldValue,
          oldTarget
        });
      }
      if (effect3.options.scheduler) {
        effect3.options.scheduler(effect3);
      } else {
        effect3();
      }
    };
    effects.forEach(run);
  }
  var isNonTrackableKeys = /* @__PURE__ */ makeMap(`__proto__,__v_isRef,__isVue`);
  var builtInSymbols = new Set(Object.getOwnPropertyNames(Symbol).map((key) => Symbol[key]).filter(isSymbol));
  var get2 = /* @__PURE__ */ createGetter();
  var readonlyGet = /* @__PURE__ */ createGetter(true);
  var arrayInstrumentations = /* @__PURE__ */ createArrayInstrumentations();
  function createArrayInstrumentations() {
    const instrumentations = {};
    ["includes", "indexOf", "lastIndexOf"].forEach((key) => {
      instrumentations[key] = function(...args) {
        const arr = toRaw(this);
        for (let i = 0, l = this.length; i < l; i++) {
          track(arr, "get", i + "");
        }
        const res = arr[key](...args);
        if (res === -1 || res === false) {
          return arr[key](...args.map(toRaw));
        } else {
          return res;
        }
      };
    });
    ["push", "pop", "shift", "unshift", "splice"].forEach((key) => {
      instrumentations[key] = function(...args) {
        pauseTracking();
        const res = toRaw(this)[key].apply(this, args);
        resetTracking();
        return res;
      };
    });
    return instrumentations;
  }
  function createGetter(isReadonly = false, shallow = false) {
    return function get3(target, key, receiver) {
      if (key === "__v_isReactive") {
        return !isReadonly;
      } else if (key === "__v_isReadonly") {
        return isReadonly;
      } else if (key === "__v_raw" && receiver === (isReadonly ? shallow ? shallowReadonlyMap : readonlyMap : shallow ? shallowReactiveMap : reactiveMap).get(target)) {
        return target;
      }
      const targetIsArray = isArray(target);
      if (!isReadonly && targetIsArray && hasOwn(arrayInstrumentations, key)) {
        return Reflect.get(arrayInstrumentations, key, receiver);
      }
      const res = Reflect.get(target, key, receiver);
      if (isSymbol(key) ? builtInSymbols.has(key) : isNonTrackableKeys(key)) {
        return res;
      }
      if (!isReadonly) {
        track(target, "get", key);
      }
      if (shallow) {
        return res;
      }
      if (isRef(res)) {
        const shouldUnwrap = !targetIsArray || !isIntegerKey(key);
        return shouldUnwrap ? res.value : res;
      }
      if (isObject(res)) {
        return isReadonly ? readonly(res) : reactive2(res);
      }
      return res;
    };
  }
  var set2 = /* @__PURE__ */ createSetter();
  function createSetter(shallow = false) {
    return function set3(target, key, value, receiver) {
      let oldValue = target[key];
      if (!shallow) {
        value = toRaw(value);
        oldValue = toRaw(oldValue);
        if (!isArray(target) && isRef(oldValue) && !isRef(value)) {
          oldValue.value = value;
          return true;
        }
      }
      const hadKey = isArray(target) && isIntegerKey(key) ? Number(key) < target.length : hasOwn(target, key);
      const result = Reflect.set(target, key, value, receiver);
      if (target === toRaw(receiver)) {
        if (!hadKey) {
          trigger(target, "add", key, value);
        } else if (hasChanged(value, oldValue)) {
          trigger(target, "set", key, value, oldValue);
        }
      }
      return result;
    };
  }
  function deleteProperty(target, key) {
    const hadKey = hasOwn(target, key);
    const oldValue = target[key];
    const result = Reflect.deleteProperty(target, key);
    if (result && hadKey) {
      trigger(target, "delete", key, void 0, oldValue);
    }
    return result;
  }
  function has(target, key) {
    const result = Reflect.has(target, key);
    if (!isSymbol(key) || !builtInSymbols.has(key)) {
      track(target, "has", key);
    }
    return result;
  }
  function ownKeys(target) {
    track(target, "iterate", isArray(target) ? "length" : ITERATE_KEY);
    return Reflect.ownKeys(target);
  }
  var mutableHandlers = {
    get: get2,
    set: set2,
    deleteProperty,
    has,
    ownKeys
  };
  var readonlyHandlers = {
    get: readonlyGet,
    set(target, key) {
      if (true) {
        console.warn(`Set operation on key "${String(key)}" failed: target is readonly.`, target);
      }
      return true;
    },
    deleteProperty(target, key) {
      if (true) {
        console.warn(`Delete operation on key "${String(key)}" failed: target is readonly.`, target);
      }
      return true;
    }
  };
  var toReactive = (value) => isObject(value) ? reactive2(value) : value;
  var toReadonly = (value) => isObject(value) ? readonly(value) : value;
  var toShallow = (value) => value;
  var getProto = (v) => Reflect.getPrototypeOf(v);
  function get$1(target, key, isReadonly = false, isShallow = false) {
    target = target[
      "__v_raw"
      /* RAW */
    ];
    const rawTarget = toRaw(target);
    const rawKey = toRaw(key);
    if (key !== rawKey) {
      !isReadonly && track(rawTarget, "get", key);
    }
    !isReadonly && track(rawTarget, "get", rawKey);
    const { has: has2 } = getProto(rawTarget);
    const wrap = isShallow ? toShallow : isReadonly ? toReadonly : toReactive;
    if (has2.call(rawTarget, key)) {
      return wrap(target.get(key));
    } else if (has2.call(rawTarget, rawKey)) {
      return wrap(target.get(rawKey));
    } else if (target !== rawTarget) {
      target.get(key);
    }
  }
  function has$1(key, isReadonly = false) {
    const target = this[
      "__v_raw"
      /* RAW */
    ];
    const rawTarget = toRaw(target);
    const rawKey = toRaw(key);
    if (key !== rawKey) {
      !isReadonly && track(rawTarget, "has", key);
    }
    !isReadonly && track(rawTarget, "has", rawKey);
    return key === rawKey ? target.has(key) : target.has(key) || target.has(rawKey);
  }
  function size(target, isReadonly = false) {
    target = target[
      "__v_raw"
      /* RAW */
    ];
    !isReadonly && track(toRaw(target), "iterate", ITERATE_KEY);
    return Reflect.get(target, "size", target);
  }
  function add(value) {
    value = toRaw(value);
    const target = toRaw(this);
    const proto = getProto(target);
    const hadKey = proto.has.call(target, value);
    if (!hadKey) {
      target.add(value);
      trigger(target, "add", value, value);
    }
    return this;
  }
  function set$1(key, value) {
    value = toRaw(value);
    const target = toRaw(this);
    const { has: has2, get: get3 } = getProto(target);
    let hadKey = has2.call(target, key);
    if (!hadKey) {
      key = toRaw(key);
      hadKey = has2.call(target, key);
    } else if (true) {
      checkIdentityKeys(target, has2, key);
    }
    const oldValue = get3.call(target, key);
    target.set(key, value);
    if (!hadKey) {
      trigger(target, "add", key, value);
    } else if (hasChanged(value, oldValue)) {
      trigger(target, "set", key, value, oldValue);
    }
    return this;
  }
  function deleteEntry(key) {
    const target = toRaw(this);
    const { has: has2, get: get3 } = getProto(target);
    let hadKey = has2.call(target, key);
    if (!hadKey) {
      key = toRaw(key);
      hadKey = has2.call(target, key);
    } else if (true) {
      checkIdentityKeys(target, has2, key);
    }
    const oldValue = get3 ? get3.call(target, key) : void 0;
    const result = target.delete(key);
    if (hadKey) {
      trigger(target, "delete", key, void 0, oldValue);
    }
    return result;
  }
  function clear() {
    const target = toRaw(this);
    const hadItems = target.size !== 0;
    const oldTarget = true ? isMap(target) ? new Map(target) : new Set(target) : void 0;
    const result = target.clear();
    if (hadItems) {
      trigger(target, "clear", void 0, void 0, oldTarget);
    }
    return result;
  }
  function createForEach(isReadonly, isShallow) {
    return function forEach(callback, thisArg) {
      const observed = this;
      const target = observed[
        "__v_raw"
        /* RAW */
      ];
      const rawTarget = toRaw(target);
      const wrap = isShallow ? toShallow : isReadonly ? toReadonly : toReactive;
      !isReadonly && track(rawTarget, "iterate", ITERATE_KEY);
      return target.forEach((value, key) => {
        return callback.call(thisArg, wrap(value), wrap(key), observed);
      });
    };
  }
  function createIterableMethod(method, isReadonly, isShallow) {
    return function(...args) {
      const target = this[
        "__v_raw"
        /* RAW */
      ];
      const rawTarget = toRaw(target);
      const targetIsMap = isMap(rawTarget);
      const isPair = method === "entries" || method === Symbol.iterator && targetIsMap;
      const isKeyOnly = method === "keys" && targetIsMap;
      const innerIterator = target[method](...args);
      const wrap = isShallow ? toShallow : isReadonly ? toReadonly : toReactive;
      !isReadonly && track(rawTarget, "iterate", isKeyOnly ? MAP_KEY_ITERATE_KEY : ITERATE_KEY);
      return {
        // iterator protocol
        next() {
          const { value, done } = innerIterator.next();
          return done ? { value, done } : {
            value: isPair ? [wrap(value[0]), wrap(value[1])] : wrap(value),
            done
          };
        },
        // iterable protocol
        [Symbol.iterator]() {
          return this;
        }
      };
    };
  }
  function createReadonlyMethod(type) {
    return function(...args) {
      if (true) {
        const key = args[0] ? `on key "${args[0]}" ` : ``;
        console.warn(`${capitalize(type)} operation ${key}failed: target is readonly.`, toRaw(this));
      }
      return type === "delete" ? false : this;
    };
  }
  function createInstrumentations() {
    const mutableInstrumentations2 = {
      get(key) {
        return get$1(this, key);
      },
      get size() {
        return size(this);
      },
      has: has$1,
      add,
      set: set$1,
      delete: deleteEntry,
      clear,
      forEach: createForEach(false, false)
    };
    const shallowInstrumentations2 = {
      get(key) {
        return get$1(this, key, false, true);
      },
      get size() {
        return size(this);
      },
      has: has$1,
      add,
      set: set$1,
      delete: deleteEntry,
      clear,
      forEach: createForEach(false, true)
    };
    const readonlyInstrumentations2 = {
      get(key) {
        return get$1(this, key, true);
      },
      get size() {
        return size(this, true);
      },
      has(key) {
        return has$1.call(this, key, true);
      },
      add: createReadonlyMethod(
        "add"
        /* ADD */
      ),
      set: createReadonlyMethod(
        "set"
        /* SET */
      ),
      delete: createReadonlyMethod(
        "delete"
        /* DELETE */
      ),
      clear: createReadonlyMethod(
        "clear"
        /* CLEAR */
      ),
      forEach: createForEach(true, false)
    };
    const shallowReadonlyInstrumentations2 = {
      get(key) {
        return get$1(this, key, true, true);
      },
      get size() {
        return size(this, true);
      },
      has(key) {
        return has$1.call(this, key, true);
      },
      add: createReadonlyMethod(
        "add"
        /* ADD */
      ),
      set: createReadonlyMethod(
        "set"
        /* SET */
      ),
      delete: createReadonlyMethod(
        "delete"
        /* DELETE */
      ),
      clear: createReadonlyMethod(
        "clear"
        /* CLEAR */
      ),
      forEach: createForEach(true, true)
    };
    const iteratorMethods = ["keys", "values", "entries", Symbol.iterator];
    iteratorMethods.forEach((method) => {
      mutableInstrumentations2[method] = createIterableMethod(method, false, false);
      readonlyInstrumentations2[method] = createIterableMethod(method, true, false);
      shallowInstrumentations2[method] = createIterableMethod(method, false, true);
      shallowReadonlyInstrumentations2[method] = createIterableMethod(method, true, true);
    });
    return [
      mutableInstrumentations2,
      readonlyInstrumentations2,
      shallowInstrumentations2,
      shallowReadonlyInstrumentations2
    ];
  }
  var [mutableInstrumentations, readonlyInstrumentations, shallowInstrumentations, shallowReadonlyInstrumentations] = /* @__PURE__ */ createInstrumentations();
  function createInstrumentationGetter(isReadonly, shallow) {
    const instrumentations = shallow ? isReadonly ? shallowReadonlyInstrumentations : shallowInstrumentations : isReadonly ? readonlyInstrumentations : mutableInstrumentations;
    return (target, key, receiver) => {
      if (key === "__v_isReactive") {
        return !isReadonly;
      } else if (key === "__v_isReadonly") {
        return isReadonly;
      } else if (key === "__v_raw") {
        return target;
      }
      return Reflect.get(hasOwn(instrumentations, key) && key in target ? instrumentations : target, key, receiver);
    };
  }
  var mutableCollectionHandlers = {
    get: /* @__PURE__ */ createInstrumentationGetter(false, false)
  };
  var readonlyCollectionHandlers = {
    get: /* @__PURE__ */ createInstrumentationGetter(true, false)
  };
  function checkIdentityKeys(target, has2, key) {
    const rawKey = toRaw(key);
    if (rawKey !== key && has2.call(target, rawKey)) {
      const type = toRawType(target);
      console.warn(`Reactive ${type} contains both the raw and reactive versions of the same object${type === `Map` ? ` as keys` : ``}, which can lead to inconsistencies. Avoid differentiating between the raw and reactive versions of an object and only use the reactive version if possible.`);
    }
  }
  var reactiveMap = /* @__PURE__ */ new WeakMap();
  var shallowReactiveMap = /* @__PURE__ */ new WeakMap();
  var readonlyMap = /* @__PURE__ */ new WeakMap();
  var shallowReadonlyMap = /* @__PURE__ */ new WeakMap();
  function targetTypeMap(rawType) {
    switch (rawType) {
      case "Object":
      case "Array":
        return 1;
      case "Map":
      case "Set":
      case "WeakMap":
      case "WeakSet":
        return 2;
      default:
        return 0;
    }
  }
  function getTargetType(value) {
    return value[
      "__v_skip"
      /* SKIP */
    ] || !Object.isExtensible(value) ? 0 : targetTypeMap(toRawType(value));
  }
  function reactive2(target) {
    if (target && target[
      "__v_isReadonly"
      /* IS_READONLY */
    ]) {
      return target;
    }
    return createReactiveObject(target, false, mutableHandlers, mutableCollectionHandlers, reactiveMap);
  }
  function readonly(target) {
    return createReactiveObject(target, true, readonlyHandlers, readonlyCollectionHandlers, readonlyMap);
  }
  function createReactiveObject(target, isReadonly, baseHandlers, collectionHandlers, proxyMap) {
    if (!isObject(target)) {
      if (true) {
        console.warn(`value cannot be made reactive: ${String(target)}`);
      }
      return target;
    }
    if (target[
      "__v_raw"
      /* RAW */
    ] && !(isReadonly && target[
      "__v_isReactive"
      /* IS_REACTIVE */
    ])) {
      return target;
    }
    const existingProxy = proxyMap.get(target);
    if (existingProxy) {
      return existingProxy;
    }
    const targetType = getTargetType(target);
    if (targetType === 0) {
      return target;
    }
    const proxy = new Proxy(target, targetType === 2 ? collectionHandlers : baseHandlers);
    proxyMap.set(target, proxy);
    return proxy;
  }
  function toRaw(observed) {
    return observed && toRaw(observed[
      "__v_raw"
      /* RAW */
    ]) || observed;
  }
  function isRef(r) {
    return Boolean(r && r.__v_isRef === true);
  }
  magic("nextTick", () => nextTick);
  magic("dispatch", (el) => dispatch.bind(dispatch, el));
  magic("watch", (el, { evaluateLater: evaluateLater2, cleanup: cleanup2 }) => (key, callback) => {
    let evaluate2 = evaluateLater2(key);
    let getter = () => {
      let value;
      evaluate2((i) => value = i);
      return value;
    };
    let unwatch = watch(getter, callback);
    cleanup2(unwatch);
  });
  magic("store", getStores);
  magic("data", (el) => scope(el));
  magic("root", (el) => closestRoot(el));
  magic("refs", (el) => {
    if (el._x_refs_proxy)
      return el._x_refs_proxy;
    el._x_refs_proxy = mergeProxies(getArrayOfRefObject(el));
    return el._x_refs_proxy;
  });
  function getArrayOfRefObject(el) {
    let refObjects = [];
    findClosest(el, (i) => {
      if (i._x_refs)
        refObjects.push(i._x_refs);
    });
    return refObjects;
  }
  var globalIdMemo = {};
  function findAndIncrementId(name) {
    if (!globalIdMemo[name])
      globalIdMemo[name] = 0;
    return ++globalIdMemo[name];
  }
  function closestIdRoot(el, name) {
    return findClosest(el, (element) => {
      if (element._x_ids && element._x_ids[name])
        return true;
    });
  }
  function setIdRoot(el, name) {
    if (!el._x_ids)
      el._x_ids = {};
    if (!el._x_ids[name])
      el._x_ids[name] = findAndIncrementId(name);
  }
  magic("id", (el, { cleanup: cleanup2 }) => (name, key = null) => {
    let cacheKey = `${name}${key ? `-${key}` : ""}`;
    return cacheIdByNameOnElement(el, cacheKey, cleanup2, () => {
      let root = closestIdRoot(el, name);
      let id = root ? root._x_ids[name] : findAndIncrementId(name);
      return key ? `${name}-${id}-${key}` : `${name}-${id}`;
    });
  });
  interceptClone((from, to) => {
    if (from._x_id) {
      to._x_id = from._x_id;
    }
  });
  function cacheIdByNameOnElement(el, cacheKey, cleanup2, callback) {
    if (!el._x_id)
      el._x_id = {};
    if (el._x_id[cacheKey])
      return el._x_id[cacheKey];
    let output = callback();
    el._x_id[cacheKey] = output;
    cleanup2(() => {
      delete el._x_id[cacheKey];
    });
    return output;
  }
  magic("el", (el) => el);
  warnMissingPluginMagic("Focus", "focus", "focus");
  warnMissingPluginMagic("Persist", "persist", "persist");
  function warnMissingPluginMagic(name, magicName, slug) {
    magic(magicName, (el) => warn(`You can't use [$${magicName}] without first installing the "${name}" plugin here: https://alpinejs.dev/plugins/${slug}`, el));
  }
  directive("modelable", (el, { expression }, { effect: effect3, evaluateLater: evaluateLater2, cleanup: cleanup2 }) => {
    let func = evaluateLater2(expression);
    let innerGet = () => {
      let result;
      func((i) => result = i);
      return result;
    };
    let evaluateInnerSet = evaluateLater2(`${expression} = __placeholder`);
    let innerSet = (val) => evaluateInnerSet(() => {
    }, { scope: { "__placeholder": val } });
    let initialValue = innerGet();
    innerSet(initialValue);
    queueMicrotask(() => {
      if (!el._x_model)
        return;
      el._x_removeModelListeners["default"]();
      let outerGet = el._x_model.get;
      let outerSet = el._x_model.setWithModifiers;
      let releaseEntanglement = entangle(
        {
          get() {
            return outerGet();
          },
          set(value) {
            outerSet(value);
          }
        },
        {
          get() {
            return innerGet();
          },
          set(value) {
            innerSet(value);
          }
        }
      );
      cleanup2(releaseEntanglement);
    });
  });
  directive("teleport", (el, { modifiers, expression }, { cleanup: cleanup2 }) => {
    if (el.tagName.toLowerCase() !== "template")
      warn("x-teleport can only be used on a <template> tag", el);
    let target = getTarget(expression);
    let clone2 = el.content.cloneNode(true).firstElementChild;
    el._x_teleport = clone2;
    clone2._x_teleportBack = el;
    el.setAttribute("data-teleport-template", true);
    clone2.setAttribute("data-teleport-target", true);
    if (el._x_forwardEvents) {
      el._x_forwardEvents.forEach((eventName) => {
        clone2.addEventListener(eventName, (e) => {
          e.stopPropagation();
          el.dispatchEvent(new e.constructor(e.type, e));
        });
      });
    }
    addScopeToNode(clone2, {}, el);
    let placeInDom = (clone3, target2, modifiers2) => {
      if (modifiers2.includes("prepend")) {
        target2.parentNode.insertBefore(clone3, target2);
      } else if (modifiers2.includes("append")) {
        target2.parentNode.insertBefore(clone3, target2.nextSibling);
      } else {
        target2.appendChild(clone3);
      }
    };
    mutateDom(() => {
      placeInDom(clone2, target, modifiers);
      skipDuringClone(() => {
        initTree(clone2);
      })();
    });
    el._x_teleportPutBack = () => {
      let target2 = getTarget(expression);
      mutateDom(() => {
        placeInDom(el._x_teleport, target2, modifiers);
      });
    };
    cleanup2(
      () => mutateDom(() => {
        clone2.remove();
        destroyTree(clone2);
      })
    );
  });
  var teleportContainerDuringClone = document.createElement("div");
  function getTarget(expression) {
    let target = skipDuringClone(() => {
      return document.querySelector(expression);
    }, () => {
      return teleportContainerDuringClone;
    })();
    if (!target)
      warn(`Cannot find x-teleport element for selector: "${expression}"`);
    return target;
  }
  var handler = () => {
  };
  handler.inline = (el, { modifiers }, { cleanup: cleanup2 }) => {
    modifiers.includes("self") ? el._x_ignoreSelf = true : el._x_ignore = true;
    cleanup2(() => {
      modifiers.includes("self") ? delete el._x_ignoreSelf : delete el._x_ignore;
    });
  };
  directive("ignore", handler);
  directive("effect", skipDuringClone((el, { expression }, { effect: effect3 }) => {
    effect3(evaluateLater(el, expression));
  }));
  function on(el, event, modifiers, callback) {
    let listenerTarget = el;
    let handler4 = (e) => callback(e);
    let options = {};
    let wrapHandler = (callback2, wrapper) => (e) => wrapper(callback2, e);
    if (modifiers.includes("dot"))
      event = dotSyntax(event);
    if (modifiers.includes("camel"))
      event = camelCase2(event);
    if (modifiers.includes("capture"))
      options.capture = true;
    if (modifiers.includes("window"))
      listenerTarget = window;
    if (modifiers.includes("document"))
      listenerTarget = document;
    if (modifiers.includes("passive")) {
      options.passive = modifiers[modifiers.indexOf("passive") + 1] !== "false";
    }
    handler4 = addDebounceOrThrottle(modifiers, handler4);
    if (modifiers.includes("prevent"))
      handler4 = wrapHandler(handler4, (next, e) => {
        e.preventDefault();
        next(e);
      });
    if (modifiers.includes("stop"))
      handler4 = wrapHandler(handler4, (next, e) => {
        e.stopPropagation();
        next(e);
      });
    if (modifiers.includes("once")) {
      handler4 = wrapHandler(handler4, (next, e) => {
        next(e);
        listenerTarget.removeEventListener(event, handler4, options);
      });
    }
    if (modifiers.includes("away") || modifiers.includes("outside")) {
      listenerTarget = document;
      handler4 = wrapHandler(handler4, (next, e) => {
        if (el.contains(e.target))
          return;
        if (e.target.isConnected === false)
          return;
        if (el.offsetWidth < 1 && el.offsetHeight < 1)
          return;
        if (el._x_isShown === false)
          return;
        next(e);
      });
    }
    if (modifiers.includes("self"))
      handler4 = wrapHandler(handler4, (next, e) => {
        e.target === el && next(e);
      });
    if (event === "submit") {
      handler4 = wrapHandler(handler4, (next, e) => {
        if (e.target._x_pendingModelUpdates) {
          e.target._x_pendingModelUpdates.forEach((fn) => fn());
        }
        next(e);
      });
    }
    if (isKeyEvent(event) || isClickEvent(event)) {
      handler4 = wrapHandler(handler4, (next, e) => {
        if (isListeningForASpecificKeyThatHasntBeenPressed(e, modifiers)) {
          return;
        }
        next(e);
      });
    }
    listenerTarget.addEventListener(event, handler4, options);
    return () => {
      listenerTarget.removeEventListener(event, handler4, options);
    };
  }
  function addDebounceOrThrottle(modifiers, handler4) {
    if (modifiers.includes("debounce")) {
      let nextModifier = modifiers[modifiers.indexOf("debounce") + 1] || "invalid-wait";
      let wait = isNumeric(nextModifier.split("ms")[0]) ? Number(nextModifier.split("ms")[0]) : 250;
      handler4 = debounce(handler4, wait);
    }
    if (modifiers.includes("throttle")) {
      let nextModifier = modifiers[modifiers.indexOf("throttle") + 1] || "invalid-wait";
      let wait = isNumeric(nextModifier.split("ms")[0]) ? Number(nextModifier.split("ms")[0]) : 250;
      handler4 = throttle(handler4, wait);
    }
    return handler4;
  }
  function dotSyntax(subject) {
    return subject.replace(/-/g, ".");
  }
  function camelCase2(subject) {
    return subject.toLowerCase().replace(/-(\w)/g, (match, char) => char.toUpperCase());
  }
  function isNumeric(subject) {
    return !Array.isArray(subject) && !isNaN(subject);
  }
  function kebabCase2(subject) {
    if ([" ", "_"].includes(
      subject
    ))
      return subject;
    return subject.replace(/([a-z])([A-Z])/g, "$1-$2").replace(/[_\s]/, "-").toLowerCase();
  }
  function isKeyEvent(event) {
    return ["keydown", "keyup"].includes(event);
  }
  function isClickEvent(event) {
    return ["contextmenu", "click", "mouse"].some((i) => event.includes(i));
  }
  function isListeningForASpecificKeyThatHasntBeenPressed(e, modifiers) {
    let keyModifiers = modifiers.filter((i) => {
      return !["window", "document", "prevent", "stop", "once", "capture", "self", "away", "outside", "passive", "preserve-scroll", "blur", "change", "lazy"].includes(i);
    });
    if (keyModifiers.includes("debounce")) {
      let debounceIndex = keyModifiers.indexOf("debounce");
      keyModifiers.splice(debounceIndex, isNumeric((keyModifiers[debounceIndex + 1] || "invalid-wait").split("ms")[0]) ? 2 : 1);
    }
    if (keyModifiers.includes("throttle")) {
      let debounceIndex = keyModifiers.indexOf("throttle");
      keyModifiers.splice(debounceIndex, isNumeric((keyModifiers[debounceIndex + 1] || "invalid-wait").split("ms")[0]) ? 2 : 1);
    }
    if (keyModifiers.length === 0)
      return false;
    if (keyModifiers.length === 1 && keyToModifiers(e.key).includes(keyModifiers[0]))
      return false;
    const systemKeyModifiers = ["ctrl", "shift", "alt", "meta", "cmd", "super"];
    const selectedSystemKeyModifiers = systemKeyModifiers.filter((modifier) => keyModifiers.includes(modifier));
    keyModifiers = keyModifiers.filter((i) => !selectedSystemKeyModifiers.includes(i));
    if (selectedSystemKeyModifiers.length > 0) {
      const activelyPressedKeyModifiers = selectedSystemKeyModifiers.filter((modifier) => {
        if (modifier === "cmd" || modifier === "super")
          modifier = "meta";
        return e[`${modifier}Key`];
      });
      if (activelyPressedKeyModifiers.length === selectedSystemKeyModifiers.length) {
        if (isClickEvent(e.type))
          return false;
        if (keyToModifiers(e.key).includes(keyModifiers[0]))
          return false;
      }
    }
    return true;
  }
  function keyToModifiers(key) {
    if (!key)
      return [];
    key = kebabCase2(key);
    let modifierToKeyMap = {
      "ctrl": "control",
      "slash": "/",
      "space": " ",
      "spacebar": " ",
      "cmd": "meta",
      "esc": "escape",
      "up": "arrow-up",
      "down": "arrow-down",
      "left": "arrow-left",
      "right": "arrow-right",
      "period": ".",
      "comma": ",",
      "equal": "=",
      "minus": "-",
      "underscore": "_"
    };
    modifierToKeyMap[key] = key;
    return Object.keys(modifierToKeyMap).map((modifier) => {
      if (modifierToKeyMap[modifier] === key)
        return modifier;
    }).filter((modifier) => modifier);
  }
  directive("model", (el, { modifiers, expression }, { effect: effect3, cleanup: cleanup2 }) => {
    let scopeTarget = el;
    if (modifiers.includes("parent")) {
      scopeTarget = findClosest(el, (element) => element !== el);
    }
    let evaluateGet = evaluateLater(scopeTarget, expression);
    let evaluateSet;
    if (typeof expression === "string") {
      evaluateSet = evaluateLater(scopeTarget, `${expression} = __placeholder`);
    } else if (typeof expression === "function" && typeof expression() === "string") {
      evaluateSet = evaluateLater(scopeTarget, `${expression()} = __placeholder`);
    } else {
      evaluateSet = () => {
      };
    }
    let getValue = () => {
      let result;
      evaluateGet((value) => result = value);
      return isGetterSetter(result) ? result.get() : result;
    };
    let setValue = (value) => {
      let result;
      evaluateGet((value2) => result = value2);
      if (isGetterSetter(result)) {
        result.set(value);
      } else {
        evaluateSet(() => {
        }, {
          scope: { "__placeholder": value }
        });
      }
    };
    if (typeof expression === "string" && el.type === "radio") {
      mutateDom(() => {
        if (!el.hasAttribute("name"))
          el.setAttribute("name", expression);
      });
    }
    let hasChangeModifier = modifiers.includes("change") || modifiers.includes("lazy");
    let hasBlurModifier = modifiers.includes("blur");
    let hasEnterModifier = modifiers.includes("enter");
    let hasExplicitEventModifiers = hasChangeModifier || hasBlurModifier || hasEnterModifier;
    let removeListener;
    if (isCloning) {
      removeListener = () => {
      };
    } else if (hasExplicitEventModifiers) {
      let listeners = [];
      let syncValue = (e) => setValue(getInputValue(el, modifiers, e, getValue()));
      if (hasChangeModifier) {
        listeners.push(on(el, "change", modifiers, syncValue));
      }
      if (hasBlurModifier) {
        listeners.push(on(el, "blur", modifiers, syncValue));
        if (el.form) {
          let form = el.form;
          let syncCallback = () => syncValue({ target: el });
          if (!form._x_pendingModelUpdates)
            form._x_pendingModelUpdates = [];
          form._x_pendingModelUpdates.push(syncCallback);
          cleanup2(() => {
            if (form._x_pendingModelUpdates) {
              form._x_pendingModelUpdates.splice(form._x_pendingModelUpdates.indexOf(syncCallback), 1);
            }
          });
        }
      }
      if (hasEnterModifier) {
        listeners.push(on(el, "keydown", modifiers, (e) => {
          if (e.key === "Enter")
            syncValue(e);
        }));
      }
      removeListener = () => listeners.forEach((remove) => remove());
    } else {
      let event = el.tagName.toLowerCase() === "select" || ["checkbox", "radio"].includes(el.type) ? "change" : "input";
      removeListener = on(el, event, modifiers, (e) => {
        setValue(getInputValue(el, modifiers, e, getValue()));
      });
    }
    if (modifiers.includes("fill")) {
      if ([void 0, null, ""].includes(getValue()) || isCheckbox(el) && Array.isArray(getValue()) || el.tagName.toLowerCase() === "select" && el.multiple) {
        setValue(
          getInputValue(el, modifiers, { target: el }, getValue())
        );
      }
    }
    if (!el._x_removeModelListeners)
      el._x_removeModelListeners = {};
    el._x_removeModelListeners["default"] = removeListener;
    cleanup2(() => el._x_removeModelListeners["default"]());
    if (el.form) {
      let removeResetListener = on(el.form, "reset", [], (e) => {
        nextTick(() => el._x_model && el._x_model.set(getInputValue(el, modifiers, { target: el }, getValue())));
      });
      cleanup2(() => removeResetListener());
    }
    el._x_model = {
      get() {
        return getValue();
      },
      set(value) {
        setValue(value);
      },
      setWithModifiers: addDebounceOrThrottle(modifiers, setValue)
    };
    el._x_forceModelUpdate = (value) => {
      if (value === void 0 && typeof expression === "string" && expression.match(/\./))
        value = "";
      mutateDom(() => {
        if (isCheckbox(el)) {
          if (Array.isArray(value)) {
            el.checked = value.some((val) => val == el.value);
          } else {
            el.checked = !!value;
          }
        } else if (isRadio(el)) {
          if (typeof value === "boolean") {
            el.checked = safeParseBoolean(el.value) === value;
          } else {
            el.checked = el.value == value;
          }
        } else {
          bind(el, "value", value);
        }
      });
    };
    effect3(() => {
      let value = getValue();
      if (modifiers.includes("unintrusive") && document.activeElement.isSameNode(el))
        return;
      el._x_forceModelUpdate(value);
    });
  });
  function getInputValue(el, modifiers, event, currentValue) {
    return mutateDom(() => {
      if (event instanceof CustomEvent && event.detail !== void 0)
        return event.detail !== null && event.detail !== void 0 ? event.detail : event.target.value;
      else if (isCheckbox(el)) {
        if (Array.isArray(currentValue)) {
          let newValue = null;
          if (modifiers.includes("number")) {
            newValue = safeParseNumber(event.target.value);
          } else if (modifiers.includes("boolean")) {
            newValue = safeParseBoolean(event.target.value);
          } else {
            newValue = event.target.value;
          }
          return event.target.checked ? currentValue.includes(newValue) ? currentValue : currentValue.concat([newValue]) : currentValue.filter((el2) => !checkedAttrLooseCompare2(el2, newValue));
        } else {
          return event.target.checked;
        }
      } else if (el.tagName.toLowerCase() === "select" && el.multiple) {
        if (modifiers.includes("number")) {
          return Array.from(event.target.selectedOptions).map((option) => {
            let rawValue = option.value || option.text;
            return safeParseNumber(rawValue);
          });
        } else if (modifiers.includes("boolean")) {
          return Array.from(event.target.selectedOptions).map((option) => {
            let rawValue = option.value || option.text;
            return safeParseBoolean(rawValue);
          });
        }
        return Array.from(event.target.selectedOptions).map((option) => {
          return option.value || option.text;
        });
      } else {
        let newValue;
        if (isRadio(el)) {
          if (event.target.checked) {
            newValue = event.target.value;
          } else {
            newValue = currentValue;
          }
        } else {
          newValue = event.target.value;
        }
        if (modifiers.includes("number")) {
          return safeParseNumber(newValue);
        } else if (modifiers.includes("boolean")) {
          return safeParseBoolean(newValue);
        } else if (modifiers.includes("trim")) {
          return newValue.trim();
        } else {
          return newValue;
        }
      }
    });
  }
  function safeParseNumber(rawValue) {
    let number = rawValue ? parseFloat(rawValue) : null;
    return isNumeric2(number) ? number : rawValue;
  }
  function checkedAttrLooseCompare2(valueA, valueB) {
    return valueA == valueB;
  }
  function isNumeric2(subject) {
    return !Array.isArray(subject) && !isNaN(subject);
  }
  function isGetterSetter(value) {
    return value !== null && typeof value === "object" && typeof value.get === "function" && typeof value.set === "function";
  }
  directive("cloak", (el) => queueMicrotask(() => mutateDom(() => el.removeAttribute(prefix("cloak")))));
  addInitSelector(() => `[${prefix("init")}]`);
  directive("init", skipDuringClone((el, { expression }, { evaluate: evaluate2 }) => {
    if (typeof expression === "string") {
      return !!expression.trim() && evaluate2(expression, {}, false);
    }
    return evaluate2(expression, {}, false);
  }));
  directive("text", (el, { expression }, { effect: effect3, evaluateLater: evaluateLater2 }) => {
    let evaluate2 = evaluateLater2(expression);
    effect3(() => {
      evaluate2((value) => {
        mutateDom(() => {
          el.textContent = value;
        });
      });
    });
  });
  directive("html", (el, { expression }, { effect: effect3, evaluateLater: evaluateLater2 }) => {
    let evaluate2 = evaluateLater2(expression);
    effect3(() => {
      evaluate2((value) => {
        mutateDom(() => {
          el.innerHTML = value ?? "";
          el._x_ignoreSelf = true;
          initTree(el);
          delete el._x_ignoreSelf;
        });
      });
    });
  });
  mapAttributes(startingWith(":", into(prefix("bind:"))));
  var handler2 = (el, { value, modifiers, expression, original }, { effect: effect3, cleanup: cleanup2 }) => {
    if (!value) {
      let bindingProviders = {};
      injectBindingProviders(bindingProviders);
      let getBindings = evaluateLater(el, expression);
      getBindings((bindings) => {
        applyBindingsObject(el, bindings, original);
      }, { scope: bindingProviders });
      return;
    }
    if (value === "key")
      return storeKeyForXFor(el, expression);
    if (el._x_inlineBindings && el._x_inlineBindings[value] && el._x_inlineBindings[value].extract) {
      return;
    }
    let evaluate2 = evaluateLater(el, expression);
    effect3(() => evaluate2((result) => {
      if (result === void 0 && typeof expression === "string" && expression.match(/\./)) {
        result = "";
      }
      mutateDom(() => bind(el, value, result, modifiers));
    }));
    cleanup2(() => {
      el._x_undoAddedClasses && el._x_undoAddedClasses();
      el._x_undoAddedStyles && el._x_undoAddedStyles();
    });
  };
  handler2.inline = (el, { value, modifiers, expression }) => {
    if (!value)
      return;
    if (!el._x_inlineBindings)
      el._x_inlineBindings = {};
    el._x_inlineBindings[value] = { expression, extract: false };
  };
  directive("bind", handler2);
  function storeKeyForXFor(el, expression) {
    el._x_keyExpression = expression;
  }
  addRootSelector(() => `[${prefix("data")}]`);
  directive("data", (el, { expression }, { cleanup: cleanup2 }) => {
    if (shouldSkipRegisteringDataDuringClone(el))
      return;
    expression = expression === "" ? "{}" : expression;
    let magicContext = {};
    injectMagics(magicContext, el);
    let dataProviderContext = {};
    injectDataProviders(dataProviderContext, magicContext);
    let data2 = evaluate(el, expression, { scope: dataProviderContext });
    if (data2 === void 0 || data2 === true)
      data2 = {};
    injectMagics(data2, el);
    let reactiveData = reactive(data2);
    initInterceptors(reactiveData);
    let undo = addScopeToNode(el, reactiveData);
    reactiveData["init"] && evaluate(el, reactiveData["init"]);
    cleanup2(() => {
      reactiveData["destroy"] && evaluate(el, reactiveData["destroy"]);
      undo();
    });
  });
  interceptClone((from, to) => {
    if (from._x_dataStack) {
      to._x_dataStack = from._x_dataStack;
      to.setAttribute("data-has-alpine-state", true);
    }
  });
  function shouldSkipRegisteringDataDuringClone(el) {
    if (!isCloning)
      return false;
    if (isCloningLegacy)
      return true;
    return el.hasAttribute("data-has-alpine-state");
  }
  directive("show", (el, { modifiers, expression }, { effect: effect3 }) => {
    let evaluate2 = evaluateLater(el, expression);
    if (!el._x_doHide)
      el._x_doHide = () => {
        mutateDom(() => {
          el.style.setProperty("display", "none", modifiers.includes("important") ? "important" : void 0);
        });
      };
    if (!el._x_doShow)
      el._x_doShow = () => {
        mutateDom(() => {
          if (el.style.length === 1 && el.style.display === "none") {
            el.removeAttribute("style");
          } else {
            el.style.removeProperty("display");
          }
        });
      };
    let hide = () => {
      el._x_doHide();
      el._x_isShown = false;
    };
    let show = () => {
      el._x_doShow();
      el._x_isShown = true;
    };
    let clickAwayCompatibleShow = () => setTimeout(show);
    let toggle = once(
      (value) => value ? show() : hide(),
      (value) => {
        if (typeof el._x_toggleAndCascadeWithTransitions === "function") {
          el._x_toggleAndCascadeWithTransitions(el, value, show, hide);
        } else {
          value ? clickAwayCompatibleShow() : hide();
        }
      }
    );
    let oldValue;
    let firstTime = true;
    effect3(() => evaluate2((value) => {
      if (!firstTime && value === oldValue)
        return;
      if (modifiers.includes("immediate"))
        value ? clickAwayCompatibleShow() : hide();
      toggle(value);
      oldValue = value;
      firstTime = false;
    }));
  });
  directive("for", (el, { expression }, { effect: effect3, cleanup: cleanup2 }) => {
    let iteratorNames = parseForExpression(expression);
    let evaluateItems = evaluateLater(el, iteratorNames.items);
    let evaluateKey = evaluateLater(
      el,
      // the x-bind:key expression is stored for our use instead of evaluated.
      el._x_keyExpression || "index"
    );
    el._x_lookup = /* @__PURE__ */ new Map();
    effect3(() => loop(el, iteratorNames, evaluateItems, evaluateKey));
    cleanup2(() => {
      el._x_lookup.forEach(
        (el2) => mutateDom(() => {
          destroyTree(el2);
          el2.remove();
        })
      );
      delete el._x_lookup;
    });
  });
  function refreshScope(scope2) {
    return (newScope) => {
      Object.entries(newScope).forEach(([key, value]) => {
        scope2[key] = value;
      });
    };
  }
  function loop(templateEl, iteratorNames, evaluateItems, evaluateKey) {
    evaluateItems((items) => {
      if (isNumeric3(items))
        items = Array.from({ length: items }, (_, i) => i + 1);
      if (items === void 0)
        items = [];
      if (items instanceof Set)
        items = Array.from(items);
      if (items instanceof Map)
        items = Array.from(items);
      let oldLookup = templateEl._x_lookup;
      let lookup = /* @__PURE__ */ new Map();
      templateEl._x_lookup = lookup;
      let hasStringKeys = isObject2(items);
      let scopeEntries = Object.entries(items).map(([index, item]) => {
        if (!hasStringKeys)
          index = parseInt(index);
        let scope2 = getIterationScopeVariables(iteratorNames, item, index, items);
        let key;
        evaluateKey((innerKey) => {
          if (typeof innerKey === "object")
            warn("x-for key cannot be an object, it must be a string or an integer", templateEl);
          if (oldLookup.has(innerKey)) {
            lookup.set(innerKey, oldLookup.get(innerKey));
            oldLookup.delete(innerKey);
          }
          key = innerKey;
        }, { scope: { index, ...scope2 } });
        return [key, scope2];
      });
      mutateDom(() => {
        oldLookup.forEach((el) => {
          destroyTree(el);
          el.remove();
        });
        let added = /* @__PURE__ */ new Set();
        let prev = templateEl;
        scopeEntries.forEach(([key, scope2]) => {
          if (lookup.has(key)) {
            let el = lookup.get(key);
            el._x_refreshXForScope(scope2);
            if (prev.nextElementSibling !== el) {
              if (prev.nextElementSibling)
                el.replaceWith(prev.nextElementSibling);
              prev.after(el);
            }
            prev = el;
            if (el._x_currentIfEl) {
              if (el.nextElementSibling !== el._x_currentIfEl)
                prev.after(el._x_currentIfEl);
              prev = el._x_currentIfEl;
            }
            return;
          }
          if (templateEl.content.children.length > 1)
            warn("x-for templates require a single root element, additional elements will be ignored.", templateEl);
          let clone2 = document.importNode(templateEl.content, true).firstElementChild;
          let reactiveScope = reactive(scope2);
          addScopeToNode(clone2, reactiveScope, templateEl);
          clone2._x_refreshXForScope = refreshScope(reactiveScope);
          lookup.set(key, clone2);
          added.add(clone2);
          prev.after(clone2);
          prev = clone2;
        });
        skipDuringClone(() => added.forEach((clone2) => initTree(clone2)))();
      });
    });
  }
  function parseForExpression(expression) {
    let forIteratorRE = /,([^,\}\]]*)(?:,([^,\}\]]*))?$/;
    let stripParensRE = /^\s*\(|\)\s*$/g;
    let forAliasRE = /([\s\S]*?)\s+(?:in|of)\s+([\s\S]*)/;
    let inMatch = expression.match(forAliasRE);
    if (!inMatch)
      return;
    let res = {};
    res.items = inMatch[2].trim();
    let item = inMatch[1].replace(stripParensRE, "").trim();
    let iteratorMatch = item.match(forIteratorRE);
    if (iteratorMatch) {
      res.item = item.replace(forIteratorRE, "").trim();
      res.index = iteratorMatch[1].trim();
      if (iteratorMatch[2]) {
        res.collection = iteratorMatch[2].trim();
      }
    } else {
      res.item = item;
    }
    return res;
  }
  function getIterationScopeVariables(iteratorNames, item, index, items) {
    let scopeVariables = {};
    if (/^\[.*\]$/.test(iteratorNames.item) && Array.isArray(item)) {
      let names = iteratorNames.item.replace("[", "").replace("]", "").split(",").map((i) => i.trim());
      names.forEach((name, i) => {
        scopeVariables[name] = item[i];
      });
    } else if (/^\{.*\}$/.test(iteratorNames.item) && !Array.isArray(item) && typeof item === "object") {
      let names = iteratorNames.item.replace("{", "").replace("}", "").split(",").map((i) => i.trim());
      names.forEach((name) => {
        scopeVariables[name] = item[name];
      });
    } else {
      scopeVariables[iteratorNames.item] = item;
    }
    if (iteratorNames.index)
      scopeVariables[iteratorNames.index] = index;
    if (iteratorNames.collection)
      scopeVariables[iteratorNames.collection] = items;
    return scopeVariables;
  }
  function isNumeric3(subject) {
    return !Array.isArray(subject) && !isNaN(subject);
  }
  function isObject2(subject) {
    return typeof subject === "object" && !Array.isArray(subject);
  }
  function handler3() {
  }
  handler3.inline = (el, { expression }, { cleanup: cleanup2 }) => {
    let root = closestRoot(el);
    if (!root)
      return;
    if (!root._x_refs)
      root._x_refs = {};
    root._x_refs[expression] = el;
    cleanup2(() => delete root._x_refs[expression]);
  };
  directive("ref", handler3);
  directive("if", (el, { expression }, { effect: effect3, cleanup: cleanup2 }) => {
    if (el.tagName.toLowerCase() !== "template")
      warn("x-if can only be used on a <template> tag", el);
    let evaluate2 = evaluateLater(el, expression);
    let show = () => {
      if (el._x_currentIfEl)
        return el._x_currentIfEl;
      let clone2 = el.content.cloneNode(true).firstElementChild;
      addScopeToNode(clone2, {}, el);
      mutateDom(() => {
        el.after(clone2);
        skipDuringClone(() => initTree(clone2))();
      });
      el._x_currentIfEl = clone2;
      el._x_undoIf = () => {
        mutateDom(() => {
          destroyTree(clone2);
          clone2.remove();
        });
        delete el._x_currentIfEl;
      };
      return clone2;
    };
    let hide = () => {
      if (!el._x_undoIf)
        return;
      el._x_undoIf();
      delete el._x_undoIf;
    };
    effect3(() => evaluate2((value) => {
      value ? show() : hide();
    }));
    cleanup2(() => el._x_undoIf && el._x_undoIf());
  });
  directive("id", (el, { expression }, { evaluate: evaluate2 }) => {
    let names = evaluate2(expression);
    names.forEach((name) => setIdRoot(el, name));
  });
  interceptClone((from, to) => {
    if (from._x_ids) {
      to._x_ids = from._x_ids;
    }
  });
  mapAttributes(startingWith("@", into(prefix("on:"))));
  directive("on", skipDuringClone((el, { value, modifiers, expression }, { cleanup: cleanup2 }) => {
    let evaluate2 = expression ? evaluateLater(el, expression) : () => {
    };
    if (el.tagName.toLowerCase() === "template") {
      if (!el._x_forwardEvents)
        el._x_forwardEvents = [];
      if (!el._x_forwardEvents.includes(value))
        el._x_forwardEvents.push(value);
    }
    let removeListener = on(el, value, modifiers, (e) => {
      evaluate2(() => {
      }, { scope: { "$event": e }, params: [e] });
    });
    cleanup2(() => removeListener());
  }));
  warnMissingPluginDirective("Collapse", "collapse", "collapse");
  warnMissingPluginDirective("Intersect", "intersect", "intersect");
  warnMissingPluginDirective("Focus", "trap", "focus");
  warnMissingPluginDirective("Mask", "mask", "mask");
  function warnMissingPluginDirective(name, directiveName, slug) {
    directive(directiveName, (el) => warn(`You can't use [x-${directiveName}] without first installing the "${name}" plugin here: https://alpinejs.dev/plugins/${slug}`, el));
  }
  alpine_default.setEvaluator(normalEvaluator);
  alpine_default.setRawEvaluator(normalRawEvaluator);
  alpine_default.setReactivityEngine({ reactive: reactive2, effect: effect2, release: stop, raw: toRaw });
  var src_default = alpine_default;
  var module_default = src_default;

  // node_modules/@alpinejs/collapse/dist/module.esm.js
  function src_default2(Alpine2) {
    Alpine2.directive("collapse", collapse);
    collapse.inline = (el, { modifiers }) => {
      if (!modifiers.includes("min"))
        return;
      el._x_doShow = () => {
      };
      el._x_doHide = () => {
      };
    };
    function collapse(el, { modifiers }) {
      let duration = modifierValue2(modifiers, "duration", 250) / 1e3;
      let floor = modifierValue2(modifiers, "min", 0);
      let fullyHide = !modifiers.includes("min");
      if (!el._x_isShown)
        el.style.height = `${floor}px`;
      if (!el._x_isShown && fullyHide)
        el.hidden = true;
      if (!el._x_isShown)
        el.style.overflow = "hidden";
      let setFunction = (el2, styles) => {
        let revertFunction = Alpine2.setStyles(el2, styles);
        return styles.height ? () => {
        } : revertFunction;
      };
      let transitionStyles = {
        transitionProperty: "height",
        transitionDuration: `${duration}s`,
        transitionTimingFunction: "cubic-bezier(0.4, 0.0, 0.2, 1)"
      };
      el._x_transition = {
        in(before = () => {
        }, after = () => {
        }) {
          if (fullyHide)
            el.hidden = false;
          if (fullyHide)
            el.style.display = null;
          let current = el.getBoundingClientRect().height;
          el.style.height = "auto";
          let full = el.getBoundingClientRect().height;
          if (current === full) {
            current = floor;
          }
          Alpine2.transition(el, Alpine2.setStyles, {
            during: transitionStyles,
            start: { height: current + "px" },
            end: { height: full + "px" }
          }, () => el._x_isShown = true, () => {
            if (Math.abs(el.getBoundingClientRect().height - full) < 1) {
              el.style.overflow = null;
            }
          });
        },
        out(before = () => {
        }, after = () => {
        }) {
          let full = el.getBoundingClientRect().height;
          Alpine2.transition(el, setFunction, {
            during: transitionStyles,
            start: { height: full + "px" },
            end: { height: floor + "px" }
          }, () => el.style.overflow = "hidden", () => {
            el._x_isShown = false;
            if (el.style.height == `${floor}px` && fullyHide) {
              el.style.display = "none";
              el.hidden = true;
            }
          });
        }
      };
    }
  }
  function modifierValue2(modifiers, key, fallback) {
    if (modifiers.indexOf(key) === -1)
      return fallback;
    const rawValue = modifiers[modifiers.indexOf(key) + 1];
    if (!rawValue)
      return fallback;
    if (key === "duration") {
      let match = rawValue.match(/([0-9]+)ms/);
      if (match)
        return match[1];
    }
    if (key === "min") {
      let match = rawValue.match(/([0-9]+)px/);
      if (match)
        return match[1];
    }
    return rawValue;
  }
  var module_default2 = src_default2;

  // ts/components/error.ts
  function registerErrorComponent() {
    const install = () => {
      window.Alpine?.data("nebulaError", (config = {}) => ({
        message: config.message ?? "",
        visible: config.visible ?? Boolean(config.message),
        autoHide: typeof config.autoHide === "number" ? config.autoHide : 0,
        _timer: null,
        init() {
          if (this.visible && this.autoHide > 0) {
            this._scheduleHide();
          }
        },
        show(message) {
          if (typeof message === "string") {
            this.message = message;
          }
          this.visible = true;
          this._scheduleHide();
        },
        hide() {
          this.visible = false;
          if (this._timer !== null) {
            window.clearTimeout(this._timer);
            this._timer = null;
          }
        },
        setMessage(message) {
          this.message = message;
        },
        _scheduleHide() {
          if (this.autoHide <= 0) return;
          if (this._timer !== null) {
            window.clearTimeout(this._timer);
          }
          this._timer = window.setTimeout(() => this.hide(), this.autoHide);
        }
      }));
    };
    if (window.Alpine) {
      install();
    } else {
      document.addEventListener("alpine:init", install);
    }
  }

  // ts/components/loading.ts
  var SIZE_CLASSES = {
    sm: "nebula-spinner--sm",
    md: "nebula-spinner--md",
    lg: "nebula-spinner--lg"
  };
  function registerLoadingComponent() {
    const install = () => {
      window.Alpine?.data("nebulaLoading", (config = {}) => {
        const size2 = config.size ?? "md";
        return {
          active: config.active ?? false,
          label: config.label ?? "Loading…",
          size: size2,
          sizeClass: SIZE_CLASSES[size2],
          minVisible: typeof config.minVisible === "number" ? config.minVisible : 200,
          _shownAt: 0,
          init() {
            if (this.active) {
              this._shownAt = Date.now();
            }
          },
          start(label) {
            if (typeof label === "string") {
              this.label = label;
            }
            this.active = true;
            this._shownAt = Date.now();
          },
          stop() {
            const elapsed = Date.now() - this._shownAt;
            const wait = Math.max(0, this.minVisible - elapsed);
            if (wait === 0) {
              this.active = false;
              return;
            }
            window.setTimeout(() => {
              this.active = false;
            }, wait);
          },
          toggle(active) {
            if (active) {
              this.start();
            } else {
              this.stop();
            }
          }
        };
      });
    };
    if (window.Alpine) {
      install();
    } else {
      document.addEventListener("alpine:init", install);
    }
  }

  // ts/components/product-selector.ts
  function registerProductSelector() {
    const install = () => {
      window.Alpine?.data("nebulaProductSelector", (config = {}) => ({
        fieldName: config.fieldName ?? "",
        fieldFormat: config.fieldFormat ?? "flat",
        selectedProducts: (config.selectedProducts ?? []).slice(),
        searchOpen: false,
        searchQuery: "",
        searchResults: [],
        searching: false,
        searchUrl: config.searchUrl ?? "",
        formKey: config.formKey ?? "",
        maxResults: config.maxResults ?? 20,
        search() {
          if (this.searchQuery.length < 2) {
            this.searchResults = [];
            return;
          }
          this.searching = true;
          const excludeIds = this.selectedProducts.map((p) => p.id).join(",");
          const url = this.searchUrl + "?q=" + encodeURIComponent(this.searchQuery) + "&exclude=" + encodeURIComponent(excludeIds) + "&limit=" + String(this.maxResults) + "&form_key=" + encodeURIComponent(this.formKey);
          fetch(url, {
            headers: { "X-Requested-With": "XMLHttpRequest" },
            credentials: "same-origin"
          }).then((resp) => resp.json()).then((data2) => {
            this.searchResults = data2.items ?? [];
            this.searching = false;
          }).catch(() => {
            this.searching = false;
          });
        },
        addProduct(product) {
          this.selectedProducts.push(product);
          this.searchResults = this.searchResults.filter((p) => p.id !== product.id);
        },
        removeProduct(index) {
          this.selectedProducts.splice(index, 1);
        }
      }));
    };
    if (window.Alpine) {
      install();
    } else {
      document.addEventListener("alpine:init", install);
    }
  }

  // ts/components/searchable-multiselect.ts
  function registerSearchableMultiselect() {
    const install = () => {
      window.Alpine?.data("nebulaMultiselect", (config = {}) => ({
        fieldName: config.fieldName ?? "",
        allOptions: config.options ?? [],
        selectedValues: (config.selected ?? []).map(String),
        label: config.label ?? "Select Options",
        search: "",
        showDropdown: false,
        modalOpen: false,
        modalSearchTerm: "",
        get inlineFilteredOptions() {
          if (!this.search) return [];
          const term = this.search.toLowerCase();
          return this.allOptions.filter((o) => (o.label ?? "").toLowerCase().includes(term) || (o.path ?? "").toLowerCase().includes(term)).slice(0, 50);
        },
        get modalFilteredOptions() {
          if (!this.modalSearchTerm) return this.allOptions;
          const term = this.modalSearchTerm.toLowerCase();
          return this.allOptions.filter((o) => (o.label ?? "").toLowerCase().includes(term) || (o.path ?? "").toLowerCase().includes(term));
        },
        get selectedItems() {
          return this.selectedValues.map((v) => this.allOptions.find((o) => String(o.value) === String(v))).filter((o) => o !== void 0);
        },
        isSelected(value) {
          return this.selectedValues.includes(String(value));
        },
        toggle(value) {
          const key = String(value);
          if (this.isSelected(key)) {
            this.selectedValues = this.selectedValues.filter((v) => v !== key);
          } else {
            this.selectedValues.push(key);
          }
        },
        selectAll() {
          this.modalFilteredOptions.forEach((opt) => {
            const v = String(opt.value);
            if (!this.isSelected(v)) this.selectedValues.push(v);
          });
        },
        deselectAll() {
          const visible = this.modalFilteredOptions.map((o) => String(o.value));
          this.selectedValues = this.selectedValues.filter((v) => !visible.includes(v));
        },
        openModal() {
          this.modalSearchTerm = "";
          this.modalOpen = true;
          this.$nextTick(() => {
            const input = this.$refs.modalSearch;
            input?.focus();
          });
        }
      }));
    };
    if (window.Alpine) {
      install();
    } else {
      document.addEventListener("alpine:init", install);
    }
  }

  // ts/components/config-form.ts
  function createNebulaFieldset(initialOpen, initialPinned, sectionId, pinUrl) {
    return {
      open: initialOpen,
      pinned: initialPinned,
      toggle() {
        this.open = !this.open;
      },
      async togglePin() {
        try {
          const formKey = document.querySelector("[name=form_key]")?.value ?? "";
          const resp = await fetch(pinUrl, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded", "X-Requested-With": "XMLHttpRequest" },
            body: new URLSearchParams({ section: sectionId, form_key: formKey })
          });
          const data2 = await resp.json();
          if (data2.success) {
            this.pinned = !!data2.pinned;
            if (data2.pinned) this.open = true;
          }
        } catch (e) {
          console.error("Pin failed:", e);
        }
      }
    };
  }
  function createNebulaConfigField(isInherited) {
    return {
      inherited: isInherited,
      onToggle(el) {
        this.inherited = el.checked;
        const row = el.closest(".nebula-config-field");
        row?.querySelectorAll("select, input:not(.nebula-inherit-checkbox), textarea").forEach((inp) => {
          inp.disabled = el.checked;
        });
      }
    };
  }
  function registerConfigForm() {
    document.addEventListener("alpine:init", () => {
      window.Alpine.data("nebulaFieldset", (initialOpen, initialPinned, sectionId, pinUrl) => createNebulaFieldset(initialOpen, initialPinned, sectionId, pinUrl));
      window.Alpine.data(
        "nebulaConfigField",
        (isInherited) => createNebulaConfigField(isInherited)
      );
    });
  }

  // ts/components/dashboard-tabs.ts
  function createDashboardTabs(config) {
    return {
      tabs: config.tabs || [],
      formKey: config.formKey || "",
      active: 0,
      displayed: 0,
      loading: false,
      error: "",
      _reqSeq: 0,
      activate(idx) {
        this.active = idx;
        this.error = "";
        const tab = this.tabs[idx];
        if (!tab) {
          return;
        }
        if (tab.content || !tab.url) {
          this.displayed = idx;
          return;
        }
        this.loading = true;
        const seq = ++this._reqSeq;
        const body = new FormData();
        body.append("form_key", this.formKey);
        fetch(tab.url, {
          method: "POST",
          body,
          credentials: "same-origin",
          headers: { "X-Requested-With": "XMLHttpRequest" }
        }).then((r) => {
          if (!r.ok) {
            throw new Error("HTTP " + r.status);
          }
          return r.text();
        }).then((html) => {
          if (seq !== this._reqSeq) {
            return;
          }
          tab.content = html;
          this.displayed = idx;
        }).catch((err) => {
          if (seq !== this._reqSeq) {
            return;
          }
          this.error = "Failed to load: " + (err?.message || "unknown");
        }).finally(() => {
          if (seq === this._reqSeq) {
            this.loading = false;
          }
        });
      }
    };
  }
  function registerDashboardTabs() {
    document.addEventListener("alpine:init", () => {
      window.Alpine.data(
        "nebulaDashboardTabs",
        (config) => createDashboardTabs(config)
      );
    });
  }

  // ts/components/config-depends.ts
  var PAYLOAD_ID = "nebula-config-depends-data";
  function readSourceValue(src) {
    if (src instanceof HTMLInputElement) {
      if (src.type === "checkbox" || src.type === "radio") {
        return src.checked ? "1" : "0";
      }
      return src.value;
    }
    if (src instanceof HTMLSelectElement) {
      return src.value;
    }
    return src.value ?? "";
  }
  function setRowState(row, visible) {
    row.style.display = visible ? "" : "none";
    row.querySelectorAll(
      "input, select, textarea"
    ).forEach((field) => {
      if (field.classList.contains("nebula-inherit-checkbox")) {
        return;
      }
      field.disabled = !visible;
      if (!visible) {
        field.classList.add("ignore-validate");
      } else {
        field.classList.remove("ignore-validate");
      }
    });
  }
  function wireDepends(map) {
    Object.keys(map).forEach((toId) => {
      const row = document.getElementById(`row_${toId}`);
      if (!row) {
        console.warn(`[nebula] depends: missing row_${toId}`);
        return;
      }
      const rules = map[toId] ?? [];
      const evaluate2 = () => {
        let visible = true;
        for (const rule of rules) {
          const src = document.getElementById(rule.from);
          if (!src) {
            visible = false;
            break;
          }
          const value = readSourceValue(src);
          let match = rule.values.indexOf(String(value)) !== -1;
          if (rule.negative) match = !match;
          if (!match) {
            visible = false;
            break;
          }
        }
        setRowState(row, visible);
      };
      rules.forEach((rule) => {
        const src = document.getElementById(rule.from);
        if (src) {
          src.addEventListener("change", evaluate2);
          src.addEventListener("input", evaluate2);
        }
      });
      evaluate2();
    });
  }
  function bootConfigDepends() {
    const node = document.getElementById(PAYLOAD_ID);
    if (!node) return;
    let map;
    try {
      map = JSON.parse(node.textContent ?? "{}");
    } catch (e) {
      console.error("[nebula] depends: invalid payload", e);
      return;
    }
    const ruleCount = Object.values(map).reduce((sum, rules) => sum + rules.length, 0);
    console.debug(`[nebula] depends boot: ${Object.keys(map).length} dependents, ${ruleCount} rules`);
    wireDepends(map);
  }

  // ts/components/media-synchronize.ts
  var STATE_RUNNING = 1;
  var STATE_FINISHED = 2;
  var STATE_NOTIFIED = 3;
  var POLL_INTERVAL_MS = 5e3;
  function readSelectValue(selector) {
    const el = document.querySelector(selector);
    return el?.value ?? "";
  }
  function setSelectsDisabled(selectors, disabled) {
    selectors.forEach((selector) => {
      const el = document.querySelector(selector);
      if (el) el.disabled = disabled;
    });
  }
  function createMediaSync(config) {
    let pollTimer = null;
    const state = {
      syncing: false,
      message: "",
      current: { storage: "", database: "" },
      baseline: { storage: "", database: "" },
      get canSync() {
        if (this.syncing) return false;
        return this.current.storage !== this.baseline.storage || this.current.database !== this.baseline.database;
      },
      init() {
        this.baseline.storage = readSelectValue(config.storageSelector);
        this.baseline.database = readSelectValue(config.databaseSelector);
        this.current.storage = this.baseline.storage;
        this.current.database = this.baseline.database;
        const refresh = () => {
          this.current.storage = readSelectValue(config.storageSelector);
          this.current.database = readSelectValue(config.databaseSelector);
        };
        const storageEl = document.querySelector(config.storageSelector);
        const databaseEl = document.querySelector(config.databaseSelector);
        if (storageEl) storageEl.addEventListener("change", refresh);
        if (databaseEl) databaseEl.addEventListener("change", refresh);
        if (config.initiallyRunning) {
          this.syncing = true;
          setSelectsDisabled([config.storageSelector, config.databaseSelector], true);
          this.poll();
        }
      },
      async sync() {
        if (!this.canSync) return;
        this.syncing = true;
        this.message = "";
        setSelectsDisabled([config.storageSelector, config.databaseSelector], true);
        const formKey = document.querySelector("[name=form_key]")?.value ?? "";
        try {
          await fetch(config.syncUrl, {
            method: "POST",
            headers: {
              "Content-Type": "application/x-www-form-urlencoded",
              "X-Requested-With": "XMLHttpRequest"
            },
            body: new URLSearchParams({
              storage: this.current.storage,
              connection: this.current.database,
              form_key: formKey
            })
          });
        } catch (e) {
          console.error("[nebula] media-sync request failed", e);
          this.syncing = false;
          setSelectsDisabled([config.storageSelector, config.databaseSelector], false);
          return;
        }
        window.setTimeout(() => this.poll(), 2e3);
      },
      async poll() {
        try {
          const resp = await fetch(config.statusUrl, {
            headers: { "X-Requested-With": "XMLHttpRequest" }
          });
          const data2 = await resp.json();
          if (Number(data2.state) === STATE_RUNNING) {
            this.message = data2.message ?? "";
            pollTimer = window.setTimeout(() => this.poll(), POLL_INTERVAL_MS);
            return;
          }
          this.syncing = false;
          this.message = "";
          setSelectsDisabled([config.storageSelector, config.databaseSelector], false);
          const finishedClean = Number(data2.state) === STATE_FINISHED || Number(data2.state) === STATE_NOTIFIED && !data2.has_errors;
          if (finishedClean) {
            this.baseline = { ...this.current };
          }
        } catch (e) {
          console.error("[nebula] media-sync poll failed", e);
          this.syncing = false;
          setSelectsDisabled([config.storageSelector, config.databaseSelector], false);
        }
      }
    };
    return state;
  }
  function registerMediaSynchronize() {
    document.addEventListener("alpine:init", () => {
      window.Alpine.data("nebulaMediaSync", (config) => createMediaSync(config));
    });
  }

  // ts/components/menu.ts
  function createNebulaMenuSidebar(config) {
    const cfg = {
      openSections: Array.isArray(config?.openSections) ? config.openSections : [],
      pinUrl: config?.pinUrl ?? "",
      reorderUrl: config?.reorderUrl ?? ""
    };
    return {
      openSections: cfg.openSections,
      init() {
        const navContainer = this.$root.closest("nav");
        if (navContainer && !navContainer.hasAttribute("aria-label")) {
          navContainer.setAttribute("aria-label", "Sidebar navigation");
        }
        const pinnedList = document.getElementById("nebula-pinned-list");
        if (pinnedList && typeof window.Sortable !== "undefined") {
          window.Sortable.create(pinnedList, {
            handle: ".nebula-drag-handle",
            animation: 150,
            ghostClass: "opacity-30",
            chosenClass: "bg-nebula-800",
            onEnd: () => {
              this.savePinOrder();
            }
          });
        }
        const nebulaStore = window.Alpine?.store ? window.Alpine.store("nebula") : null;
        if (nebulaStore && window.Alpine && typeof window.Alpine.effect === "function") {
          window.Alpine.effect(() => {
            if (nebulaStore.sidebarCollapsed) {
              this.openSections = [];
            }
          });
        }
      },
      toggle(id) {
        const nebulaStore = window.Alpine?.store ? window.Alpine.store("nebula") : null;
        if (nebulaStore?.sidebarCollapsed) {
          if (!this.openSections.includes(id)) {
            this.openSections.push(id);
          }
          nebulaStore.toggleSidebar();
          return;
        }
        const i = this.openSections.indexOf(id);
        i > -1 ? this.openSections.splice(i, 1) : this.openSections.push(id);
      },
      isOpen(id) {
        return this.openSections.includes(id);
      },
      async togglePin(itemId) {
        try {
          const formKey = document.querySelector('[name="form_key"]')?.value ?? "";
          const resp = await fetch(cfg.pinUrl, {
            method: "POST",
            headers: {
              "Content-Type": "application/x-www-form-urlencoded",
              "X-Requested-With": "XMLHttpRequest"
            },
            body: new URLSearchParams({ item_id: itemId, form_key: formKey })
          });
          const data2 = await resp.json();
          if (data2.success) {
            window.location.reload();
          }
        } catch (e) {
          console.error("Pin toggle failed:", e);
        }
      },
      async savePinOrder() {
        const items = document.querySelectorAll("#nebula-pinned-list [data-pin-id]");
        const order = Array.from(items).map((el) => el.dataset["pinId"]);
        try {
          const formKey = document.querySelector('[name="form_key"]')?.value ?? "";
          await fetch(cfg.reorderUrl, {
            method: "POST",
            headers: {
              "Content-Type": "application/x-www-form-urlencoded",
              "X-Requested-With": "XMLHttpRequest"
            },
            body: new URLSearchParams({ order: JSON.stringify(order), form_key: formKey })
          });
        } catch (e) {
          console.error("Reorder failed:", e);
        }
      }
    };
  }
  function registerNebulaMenu() {
    document.addEventListener("alpine:init", () => {
      window.Alpine.data(
        "nebulaMenuSidebar",
        (config) => createNebulaMenuSidebar(config)
      );
      window.Alpine.data("nebulaMenuTop", () => ({}));
    });
  }

  // ts/components/store-picker.ts
  function createStorePicker(config) {
    const rawNodes = Array.isArray(config?.nodes) ? config.nodes : [];
    const nodes = rawNodes.map((n, i) => ({ ...n, key: `${n.depth}:${n.value ?? "g"}:${i}` }));
    const initial = Array.isArray(config?.value) ? config.value.map(String) : [];
    return {
      fieldName: config?.fieldName ?? "store_id",
      value: initial,
      nodes,
      search: "",
      get visibleNodes() {
        const q = this.search.trim().toLowerCase();
        if (q === "") return this.nodes;
        const keepStores = this.nodes.map(
          (n) => !n.group && n.label.toLowerCase().includes(q)
        );
        const keepGroups = this.nodes.map(() => false);
        for (let i = 0; i < this.nodes.length; i++) {
          if (!keepStores[i]) continue;
          const storeDepth = this.nodes[i].depth;
          for (let j = i - 1; j >= 0; j--) {
            const node = this.nodes[j];
            if (!node.group) continue;
            if (node.depth < storeDepth) {
              keepGroups[j] = true;
            }
          }
        }
        return this.nodes.filter((_, i) => keepStores[i] || keepGroups[i]);
      },
      isSelected(v) {
        return v !== null && this.value.includes(v);
      },
      toggle(v) {
        if (v === null) return;
        const i = this.value.indexOf(v);
        if (i === -1) this.value.push(v);
        else this.value.splice(i, 1);
      }
    };
  }
  function registerStorePicker() {
    document.addEventListener("alpine:init", () => {
      window.Alpine.data("nebulaStorePicker", (config) => createStorePicker(config));
    });
  }

  // ts/components/widget/wizard.ts
  function createWizardState(opts) {
    const ctl = {
      state: opts.initial,
      fields: opts.paramsByType[opts.initial.instance_type] ?? [],
      availableChoosers: opts.availableChoosers,
      pageGroupOptions: opts.pageGroupOptions ?? [],
      pageLayoutOptions: opts.pageLayoutOptions ?? [],
      typeCodeMap: opts.typeCodeMap ?? {},
      // Tracks the widget type the wizard was last configured for.
      // Initialized to the loaded type so the first $watch trip (if Alpine
      // ever fires one during init) doesn't look like a "type change" and
      // wipe edit-flow page_groups.
      _previousType: opts.initial.instance_type,
      currentCode() {
        return this.typeCodeMap[this.state.instance_type] ?? "";
      },
      rowEntityKind(row) {
        switch (row.page_group) {
          case "pages":
            return "page";
          case "page_layouts":
            return "page_layout";
          case "anchor_categories":
          case "notanchor_categories":
            return "category";
          case "":
            return "none";
          default:
            return row.page_group.endsWith("_products") ? "product" : "none";
        }
      },
      hasTypeAndTheme() {
        return !!this.state.instance_type && !!this.state.theme_id;
      },
      paramsEnabled() {
        return this.hasTypeAndTheme();
      },
      isValid() {
        if (!this.hasTypeAndTheme()) return false;
        if (this.state.title.trim().length === 0) return false;
        if (!this.fields.every(
          (f) => !f.required || !this.isFieldVisible(f) || hasValue(this.state.parameters[f.name])
        )) {
          return false;
        }
        return this.state.page_groups.every((r) => this.isPageGroupRowValid(r));
      },
      isPageGroupRowValid(row) {
        if (!row.page_group) return false;
        if (!row.block) return false;
        if (!row.template) return false;
        const kind = this.rowEntityKind(row);
        if (kind === "page_layout" && !row.layout_handle) return false;
        if (kind === "page" && (!row.page_id || row.page_id === "0")) return false;
        if ((kind === "category" || kind === "product") && row.for === "specific" && !row.entities) {
          return false;
        }
        return true;
      },
      isChooserAvailable(chooserAlias) {
        return !!chooserAlias && this.availableChoosers.includes(chooserAlias);
      },
      isFieldVisible(field) {
        if (field.visible === false) return false;
        if (!field.depends || field.depends.length === 0) return true;
        return field.depends.every(({ param, value }) => {
          const current = this.state.parameters[param];
          if (Array.isArray(current)) return current.includes(value);
          return String(current ?? "") === value;
        });
      },
      containersForCurrentType() {
        return opts.containersByType?.[this.state.instance_type] ?? [];
      },
      templatesForContainer(containerName) {
        if (!containerName) return [];
        const byType = opts.containerTemplatesByType?.[this.state.instance_type];
        return byType?.[containerName] ?? [];
      },
      loadFieldsForType() {
        const previousType = this._previousType;
        this._previousType = this.state.instance_type;
        if (this.state.instance_id === null && previousType !== "" && previousType !== this.state.instance_type) {
          this.state.page_groups = [];
          this.state.parameter_labels = {};
        }
        this.fields = opts.paramsByType[this.state.instance_type] ?? [];
        for (const f of this.fields) {
          if (this.state.parameters[f.name] === void 0) {
            this.state.parameters[f.name] = f.default;
          }
        }
      },
      handleChooserSelected(detail) {
        if (detail.fieldName.startsWith("pg:")) {
          const [, idxStr, mode] = detail.fieldName.split(":");
          const idx = Number(idxStr);
          const row = this.state.page_groups[idx];
          if (!row) return;
          if (mode === "page") {
            row.page_id = detail.value;
            row.page_label = detail.label;
          } else if (mode === "entities") {
            const id = detail.value.replace(/^(category|product)\//, "");
            const ids = row.entities ? row.entities.split(",").map((s) => s.trim()).filter(Boolean) : [];
            if (!ids.includes(id)) {
              ids.push(id);
              row.entities = ids.join(",");
            }
          }
          return;
        }
        this.state.parameters[detail.fieldName] = detail.value;
        this.state.parameter_labels[detail.fieldName] = detail.label;
      },
      addPageGroup() {
        this.state.page_groups.push({
          page_group: "",
          block: "",
          template: "",
          for: "all",
          page_id: "0",
          entities: "",
          page_label: "",
          layout_handle: "",
          containers: [],
          loading: false
        });
      },
      removePageGroup(idx) {
        this.state.page_groups.splice(idx, 1);
      },
      async onPageGroupChanged(row) {
        row.block = "";
        row.template = "";
        row.containers = [];
        if (!row.page_group || !opts.blocksUrl) {
          return;
        }
        const handle = opts.layoutHandleMap?.[row.page_group] ?? "";
        if (!handle) {
          row.containers = this.containersForCurrentType();
          return;
        }
        row.loading = true;
        try {
          const code = this.currentCode();
          const themeId = this.state.theme_id ? String(this.state.theme_id) : "";
          const body = new URLSearchParams({
            layout: handle,
            code,
            theme_id: themeId,
            isAjax: "true",
            form_key: getFormKey()
          });
          const resp = await fetch(opts.blocksUrl, {
            method: "POST",
            headers: { "X-Requested-With": "XMLHttpRequest" },
            body,
            credentials: "same-origin"
          });
          if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
          const html = await resp.text();
          row.containers = parseContainerSelect(html);
          if (row.containers.length === 0) {
            row.containers = this.containersForCurrentType();
          }
        } catch (e) {
          console.warn("[nebula widget wizard] Blocks AJAX failed, falling back to static containers", e);
          row.containers = this.containersForCurrentType();
        } finally {
          row.loading = false;
        }
      },
      containersForRow(row) {
        return row.containers && row.containers.length > 0 ? row.containers : this.containersForCurrentType();
      },
      buildPostBody() {
        const body = new URLSearchParams();
        if (this.state.instance_id !== null) body.set("instance_id", String(this.state.instance_id));
        body.set("instance_type", this.state.instance_type);
        body.set("code", this.currentCode());
        body.set("theme_id", String(this.state.theme_id ?? ""));
        body.set("title", this.state.title);
        body.set("sort_order", String(this.state.sort_order));
        for (const id of this.state.store_ids) body.append("store_ids[]", id);
        for (const [name, value] of Object.entries(this.state.parameters)) {
          if (Array.isArray(value)) {
            for (const v of value) body.append(`parameters[${name}][]`, v);
          } else {
            body.set(`parameters[${name}]`, value);
          }
        }
        this.state.page_groups.forEach((pg, i) => {
          if (!pg.page_group) return;
          body.set(`widget_instance[${i}][page_group]`, pg.page_group);
          const k = pg.page_group;
          body.set(`widget_instance[${i}][${k}][page_id]`, pg.page_id || "0");
          body.set(`widget_instance[${i}][${k}][for]`, pg.for);
          body.set(`widget_instance[${i}][${k}][block]`, pg.block);
          body.set(`widget_instance[${i}][${k}][template]`, pg.template);
          if (pg.for === "specific") {
            body.set(`widget_instance[${i}][${k}][entities]`, pg.entities);
          }
          if (k === "pages" || k === "page_layouts") {
            body.set(`widget_instance[${i}][${k}][layout_handle]`, pg.layout_handle);
          }
        });
        return body;
      }
    };
    return ctl;
  }
  function hasValue(v) {
    if (v === void 0) return false;
    if (Array.isArray(v)) return v.length > 0;
    return v.length > 0;
  }
  function parseContainerSelect(html) {
    if (!html.trim()) return [];
    const doc = new DOMParser().parseFromString(html, "text/html");
    const opts = Array.from(doc.querySelectorAll("option"));
    return opts.map((o) => o.getAttribute("value") ?? "").filter((v) => v !== "");
  }
  function getFormKey() {
    const el = document.querySelector('input[name="form_key"]');
    return el?.value ?? "";
  }
  function registerWidgetWizard() {
    document.addEventListener("alpine:init", () => {
      window.Alpine.data("nebulaWidgetWizard", (opts) => createWizardState(opts));
    });
  }

  // ts/components/test-connection.ts
  function extractConfig(button) {
    const raw2 = button.dataset.mageInit;
    if (!raw2) return null;
    try {
      const parsed = JSON.parse(raw2);
      const tc = parsed["testConnection"];
      if (!tc?.url || !tc?.elementId) return null;
      return tc;
    } catch (e) {
      console.warn("[nebula] test-connection: invalid data-mage-init", e);
      return null;
    }
  }
  function gatherParams(fieldMappingJson) {
    const params = {};
    let mapping;
    try {
      mapping = JSON.parse(fieldMappingJson);
    } catch {
      return params;
    }
    Object.entries(mapping).forEach(([key, domId]) => {
      const el = document.getElementById(domId);
      params[key] = el?.value ?? "";
    });
    return params;
  }
  async function runTest(button, config) {
    const resultSpan = document.getElementById(`${config.elementId}_result`);
    const originalText = resultSpan?.textContent ?? "";
    button.disabled = true;
    button.classList.remove("nebula-test-success", "nebula-test-fail");
    button.classList.add("nebula-test-pending");
    if (resultSpan) resultSpan.textContent = "Testing…";
    const formKey = document.querySelector("[name=form_key]")?.value ?? "";
    const params = gatherParams(config.fieldMapping);
    params["form_key"] = formKey;
    let response;
    try {
      const resp = await fetch(config.url, {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
          "X-Requested-With": "XMLHttpRequest"
        },
        body: new URLSearchParams(params)
      });
      response = await resp.json();
    } catch (e) {
      console.error("[nebula] test-connection request failed", e);
      button.classList.remove("nebula-test-pending");
      button.classList.add("nebula-test-fail");
      if (resultSpan) resultSpan.textContent = config.failedText || originalText;
      button.disabled = false;
      return;
    }
    button.classList.remove("nebula-test-pending");
    if (response.success) {
      button.classList.add("nebula-test-success");
      if (resultSpan) resultSpan.textContent = config.successText || originalText;
    } else {
      button.classList.add("nebula-test-fail");
      if (resultSpan) resultSpan.textContent = config.failedText || originalText;
      if (response.errorMessage) {
        window.alert(response.errorMessage);
      }
    }
    button.disabled = false;
  }
  function wireButton(button) {
    if (button.dataset.nebulaTestConnectionBound === "1") return;
    const config = extractConfig(button);
    if (!config) return;
    button.dataset.nebulaTestConnectionBound = "1";
    button.addEventListener("click", (e) => {
      e.preventDefault();
      void runTest(button, config);
    });
  }
  function bootTestConnection() {
    const buttons = document.querySelectorAll('button[data-mage-init*="testConnection"]');
    if (buttons.length === 0) return;
    console.debug(`[nebula] test-connection boot: ${buttons.length} button(s)`);
    buttons.forEach((btn) => wireButton(btn));
  }

  // ts/components/image-preview.ts
  var MODAL_ID = "nebula-image-preview-modal";
  function ensureModal() {
    let modal = document.getElementById(MODAL_ID);
    if (modal) return modal;
    modal = document.createElement("div");
    modal.id = MODAL_ID;
    modal.style.cssText = [
      "position:fixed",
      "inset:0",
      "z-index:9999",
      "display:none",
      "align-items:center",
      "justify-content:center",
      "background:rgba(0,0,0,0.75)",
      "backdrop-filter:blur(4px)",
      "padding:2rem"
    ].join(";");
    const panel = document.createElement("div");
    panel.style.cssText = [
      "position:relative",
      "max-width:min(90vw,1200px)",
      "max-height:90vh",
      "display:flex",
      "align-items:center",
      "justify-content:center"
    ].join(";");
    const img = document.createElement("img");
    img.id = `${MODAL_ID}-img`;
    img.alt = "";
    img.style.cssText = [
      "max-width:100%",
      "max-height:90vh",
      "border-radius:0.75rem",
      "box-shadow:0 25px 50px -12px rgba(0,0,0,0.5)",
      "display:block"
    ].join(";");
    const close = document.createElement("button");
    close.type = "button";
    close.setAttribute("aria-label", "Close preview");
    close.innerHTML = "&times;";
    close.style.cssText = [
      "position:absolute",
      "top:0.5rem",
      "right:0.5rem",
      "width:2rem",
      "height:2rem",
      "border-radius:9999px",
      "background:rgba(0,0,0,0.55)",
      "color:#fff",
      "border:0",
      "font-size:1.5rem",
      "line-height:1",
      "cursor:pointer",
      "display:flex",
      "align-items:center",
      "justify-content:center",
      "transition:background 0.15s ease"
    ].join(";");
    close.addEventListener("mouseenter", () => {
      close.style.background = "rgba(0,0,0,0.8)";
    });
    close.addEventListener("mouseleave", () => {
      close.style.background = "rgba(0,0,0,0.55)";
    });
    close.addEventListener("click", () => hideModal());
    panel.appendChild(close);
    panel.appendChild(img);
    modal.appendChild(panel);
    modal.addEventListener("click", (e) => {
      if (e.target === modal) hideModal();
    });
    document.body.appendChild(modal);
    return modal;
  }
  function hideModal() {
    const modal = document.getElementById(MODAL_ID);
    if (modal) modal.style.display = "none";
  }
  function showModal(src) {
    const modal = ensureModal();
    const img = document.getElementById(`${MODAL_ID}-img`);
    if (img) img.src = src;
    modal.style.display = "flex";
  }
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") hideModal();
  });
  function wireLink(link) {
    if (link.dataset.nebulaImagePreviewBound === "1") return;
    link.dataset.nebulaImagePreviewBound = "1";
    link.onclick = null;
    link.addEventListener("click", (e) => {
      e.preventDefault();
      const img = link.querySelector("img");
      const src = link.getAttribute("href") ?? img?.getAttribute("src") ?? "";
      if (src) showModal(src);
    });
  }
  function bootImagePreview() {
    if (typeof window.imagePreview !== "function") {
      window.imagePreview = () => false;
    }
    const links = document.querySelectorAll("a[previewlinkid]");
    links.forEach(wireLink);
  }

  // ts/components/vendor-compat.ts
  function installVendorCompat() {
    const w = window;
    if (typeof w.setLocation !== "function") {
      w.setLocation = (url) => {
        window.location.href = url;
      };
    }
    if (typeof w.$ !== "function") {
      w.$ = (id) => document.getElementById(id);
    }
  }

  // ts/fields/base.ts
  function createBaseField(config = {}) {
    return {
      value: config.value !== void 0 ? config.value : config.default !== void 0 ? config.default : "",
      fieldName: config.fieldName ?? "",
      disabled: !!config.disabled,
      required: !!config.required,
      validation: config.validation ?? {},
      error: null,
      _modelKey: null,
      init() {
        if (this.fieldName) {
          this._modelKey = "field:" + this.fieldName;
          const store2 = window.Alpine.store("nebulaModels");
          if (store2) store2.register(this._modelKey, this);
        }
      },
      destroy() {
        if (this._modelKey) {
          const store2 = window.Alpine.store("nebulaModels");
          if (store2) store2.unregister(this._modelKey);
        }
      },
      serialize() {
        if (!this.fieldName || this.disabled) return [];
        const raw2 = this.value;
        const value = raw2 === null || raw2 === void 0 ? "" : typeof raw2 === "string" || typeof raw2 === "number" || typeof raw2 === "boolean" ? raw2 : String(raw2);
        return [{ name: this.fieldName, value }];
      },
      validate() {
        const root = this.$el;
        if (!root || !window.Nebula || typeof window.Nebula.validateField !== "function") {
          return true;
        }
        const input = root.querySelector("[data-validate]");
        if (!input) return true;
        return window.Nebula.validateField(input);
      }
    };
  }
  function installBaseField() {
    window.NebulaField = window.NebulaField ?? {
      base: (config) => createBaseField(config ?? {})
    };
  }

  // ts/fields/checkbox.ts
  function registerCheckboxField() {
    document.addEventListener("alpine:init", () => {
      window.Alpine.data("nebulaField_checkbox", (config = {}) => {
        const base = createBaseField(config);
        return Object.assign(base, {
          value: Boolean(config.value),
          serialize() {
            if (!this.fieldName || this.disabled) return [];
            return [{ name: this.fieldName, value: this.value ? "1" : "0" }];
          }
        });
      });
    });
  }

  // ts/fields/color.ts
  function registerColorField() {
    document.addEventListener("alpine:init", () => {
      window.Alpine.data("nebulaField_color", (config = {}) => createBaseField(config));
    });
  }

  // ts/fields/column.ts
  var baseColumnData = (config) => ({
    value: config.value ?? "",
    label: config.label ?? "",
    name: config.name ?? "",
    sortable: Boolean(config.sortable),
    visible: config.visible !== false
  });
  var columnFactories = {
    text: (c) => baseColumnData(c),
    badge: (c) => {
      const b = baseColumnData(c);
      const variant = typeof c["variant"] === "string" ? c["variant"] : "default";
      const map = c["map"] ?? {};
      return Object.assign(b, {
        variant,
        map,
        getBadgeClass() {
          return this.map[String(this.value)] ?? this.variant;
        }
      });
    },
    actions: (c) => {
      const b = baseColumnData(c);
      const actions = Array.isArray(c["actions"]) ? c["actions"] : [];
      return Object.assign(b, { actions });
    },
    boolean: (c) => {
      const b = baseColumnData(c);
      const trueLabel = typeof c["trueLabel"] === "string" ? c["trueLabel"] : "Yes";
      const falseLabel = typeof c["falseLabel"] === "string" ? c["falseLabel"] : "No";
      return Object.assign(b, {
        getDisplayValue() {
          return this.value ? trueLabel : falseLabel;
        }
      });
    },
    date: (c) => {
      const b = baseColumnData(c);
      const format = typeof c["format"] === "string" ? c["format"] : "YYYY-MM-DD";
      return Object.assign(b, {
        format,
        getFormattedDate() {
          if (!this.value) return "";
          try {
            return new Date(String(this.value)).toLocaleDateString();
          } catch {
            return String(this.value);
          }
        }
      });
    },
    price: (c) => {
      const b = baseColumnData(c);
      const currency = typeof c["currency"] === "string" ? c["currency"] : "USD";
      return Object.assign(b, {
        currency,
        getFormattedPrice() {
          const n = parseFloat(String(this.value));
          if (isNaN(n)) return "";
          try {
            return n.toLocaleString(void 0, { style: "currency", currency: this.currency });
          } catch {
            return n.toFixed(2);
          }
        }
      });
    },
    link: (c) => {
      const b = baseColumnData(c);
      return Object.assign(b, {
        href: typeof c["href"] === "string" ? c["href"] : "#",
        target: typeof c["target"] === "string" ? c["target"] : "_self"
      });
    },
    thumbnail: (c) => {
      const b = baseColumnData(c);
      return Object.assign(b, {
        src: typeof c["src"] === "string" ? c["src"] : "",
        alt: typeof c["alt"] === "string" ? c["alt"] : "",
        width: typeof c["width"] === "number" ? c["width"] : 50,
        height: typeof c["height"] === "number" ? c["height"] : 50
      });
    }
  };
  function registerColumnComponents() {
    document.addEventListener("alpine:init", () => {
      Object.keys(columnFactories).forEach((type) => {
        window.Alpine.data("nebulaColumn_" + type, (config = {}) => {
          const factory = columnFactories[type];
          if (!factory) return baseColumnData(config);
          return factory(config);
        });
      });
    });
  }

  // ts/fields/date.ts
  function registerDateField() {
    document.addEventListener("alpine:init", () => {
      window.Alpine.data("nebulaField_date", (config = {}) => createBaseField(config));
    });
  }

  // ts/fields/hidden.ts
  function registerHiddenField() {
    document.addEventListener("alpine:init", () => {
      window.Alpine.data("nebulaField_hidden", (config = {}) => createBaseField(config));
    });
  }

  // ts/fields/layout.ts
  var widthClassMap = {
    "1/2": "nebula-col--6",
    "1/3": "nebula-col--4",
    "2/3": "nebula-col--8",
    "1/4": "nebula-col--3",
    "3/4": "nebula-col--9",
    full: "nebula-col--12"
  };
  function registerLayoutComponent() {
    document.addEventListener("alpine:init", () => {
      window.Alpine.data("nebulaLayout", (layoutTree) => ({
        layout: layoutTree ?? {},
        activeTab: 0,
        getWidthClass(w) {
          return widthClassMap[w] ?? "nebula-col--12";
        },
        setActiveTab(i) {
          this.activeTab = i;
        },
        isActiveTab(i) {
          return this.activeTab === i;
        },
        renderNode(node) {
          if (!node || !node.type) return null;
          const props = { ...node.props ?? {} };
          if (node.props && typeof node.props.width === "string") {
            props.colClass = widthClassMap[node.props.width] ?? "nebula-col--12";
          }
          const result = {
            type: node.type,
            props,
            children: []
          };
          if (node.children && node.children.length) {
            result.children = node.children.map((c) => this.renderNode(c)).filter((x) => x !== null);
          }
          return result;
        }
      }));
    });
  }

  // ts/fields/multiselect.ts
  function normalizeTreeNodes(options) {
    return options.map((o) => {
      const raw2 = o;
      return {
        value: raw2.value === null || raw2.value === void 0 ? null : String(raw2.value),
        label: String(raw2.label ?? ""),
        depth: typeof raw2.depth === "number" ? raw2.depth : 0,
        group: raw2.group === true,
        all: raw2.all === true
      };
    });
  }
  function createTreeUi(options) {
    const nodes = normalizeTreeNodes(options);
    return {
      search: "",
      nodes,
      visibleNodes() {
        const q = this.search.trim().toLowerCase();
        if (q === "") {
          return this.nodes;
        }
        const matched = [];
        this.nodes.forEach((n, i) => {
          if (!n.group && n.label.toLowerCase().includes(q)) {
            matched.push(i);
          }
        });
        if (matched.length === 0) {
          return [];
        }
        const keep = new Set(matched);
        for (const idx of matched) {
          let minDepthFound = this.nodes[idx]?.depth ?? 0;
          for (let j = idx - 1; j >= 0; j--) {
            const n = this.nodes[j];
            if (!n) continue;
            if (n.group && n.depth < minDepthFound) {
              keep.add(j);
              minDepthFound = n.depth;
              if (n.depth === 0) break;
            }
          }
        }
        const sorted = Array.from(keep).sort((a, b) => a - b);
        return sorted.map((i) => this.nodes[i]);
      },
      toggle(currentValue, node) {
        if (node.value === null) {
          return currentValue;
        }
        const v = node.value;
        const isSelected = currentValue.includes(v);
        if (node.all) {
          return isSelected ? currentValue.filter((x) => x !== v) : [v];
        }
        const allValues = new Set(
          this.nodes.filter((n) => n.all && n.value !== null).map((n) => n.value)
        );
        const withoutAll = currentValue.filter((x) => !allValues.has(x));
        if (isSelected) {
          return withoutAll.filter((x) => x !== v);
        }
        return withoutAll.concat(v);
      }
    };
  }
  function registerMultiselectField() {
    document.addEventListener("alpine:init", () => {
      window.Alpine.data("nebulaField_multiselect", (config = {}) => {
        const base = createBaseField(config);
        const options = Array.isArray(config.options) ? config.options : [];
        const initial = Array.isArray(config.value) ? config.value.slice() : [];
        const tree = config.tree === true;
        const component = Object.assign(base, {
          options,
          tree,
          treeUi: tree ? createTreeUi(options) : null,
          value: initial,
          serialize() {
            if (!this.fieldName || this.disabled) return [];
            const values = Array.isArray(this.value) ? this.value : [];
            return values.map((v) => ({
              name: this.fieldName + "[]",
              value: typeof v === "string" || typeof v === "number" || typeof v === "boolean" ? v : String(v)
            }));
          }
        });
        return component;
      });
      window.Alpine.data("nebulaMultiselectUi", (opts = {}) => ({
        search: "",
        showDropdown: false,
        modalOpen: false,
        modalSearchTerm: "",
        compactThreshold: opts.compactThreshold ?? 12,
        isSelected(v) {
          const root = this.$root;
          const parentEl = root?.parentElement?.closest("[x-data]");
          const parent = parentEl ? window.Alpine.$data(parentEl) : {};
          const values = Array.isArray(parent.value) ? parent.value : [];
          return values.includes(String(v));
        },
        toggle(raw2) {
          const root = this.$root;
          const parentEl = root?.parentElement?.closest("[x-data]");
          if (!parentEl) return;
          const parent = window.Alpine.$data(parentEl);
          const v = String(raw2);
          const current = Array.isArray(parent.value) ? parent.value : [];
          if (current.includes(v)) {
            parent.value = current.filter((item) => item !== v);
          } else {
            parent.value = current.concat(v);
          }
        }
      }));
    });
  }

  // ts/fields/number.ts
  function registerNumberField() {
    document.addEventListener("alpine:init", () => {
      window.Alpine.data("nebulaField_number", (config = {}) => {
        const base = createBaseField(config);
        const step = typeof config["step"] === "number" ? config["step"] : 1;
        const min = config["min"] ?? null;
        const max = config["max"] ?? null;
        return Object.assign(base, { step, min, max });
      });
    });
  }

  // ts/fields/select.ts
  function registerSelectField() {
    document.addEventListener("alpine:init", () => {
      window.Alpine.data("nebulaField_select", (config = {}) => {
        const base = createBaseField(config);
        const options = Array.isArray(config.options) ? config.options : [];
        return Object.assign(base, { options });
      });
    });
  }

  // ts/fields/text.ts
  function registerTextField() {
    document.addEventListener("alpine:init", () => {
      window.Alpine.data(
        "nebulaField_text",
        (config = {}) => createBaseField(config)
      );
    });
  }

  // ts/fields/textarea.ts
  function registerTextareaField() {
    document.addEventListener("alpine:init", () => {
      window.Alpine.data("nebulaField_textarea", (config = {}) => {
        const base = createBaseField(config);
        const rows = typeof config["rows"] === "number" ? config["rows"] : 4;
        return Object.assign(base, { rows });
      });
    });
  }

  // ts/fields/toggle.ts
  function registerToggleField() {
    document.addEventListener("alpine:init", () => {
      window.Alpine.data("nebulaField_toggle", (config = {}) => {
        const base = createBaseField(config);
        return Object.assign(base, {
          value: Boolean(config.value),
          serialize() {
            if (!this.fieldName || this.disabled) return [];
            return [{ name: this.fieldName, value: this.value ? "1" : "0" }];
          }
        });
      });
    });
  }

  // ts/modal.ts
  var sizeClasses = {
    sm: "max-w-md",
    md: "max-w-xl",
    lg: "max-w-3xl",
    xl: "max-w-5xl",
    full: "max-w-7xl"
  };
  var FOCUSABLE_SELECTOR = 'a[href], area[href], input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), button:not([disabled]), iframe, object, embed, [tabindex]:not([tabindex^="-"]), [contenteditable=true]';
  function installFocusTrap(panel) {
    const handler4 = (event) => {
      if (event.key !== "Tab") return;
      const focusables = Array.from(
        panel.querySelectorAll(FOCUSABLE_SELECTOR)
      ).filter((el) => el.offsetParent !== null || el === document.activeElement);
      if (focusables.length === 0) {
        event.preventDefault();
        return;
      }
      const first = focusables[0];
      const last = focusables[focusables.length - 1];
      const active = document.activeElement;
      if (event.shiftKey && active === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && active === last) {
        event.preventDefault();
        first.focus();
      }
    };
    panel.addEventListener("keydown", handler4);
    return () => panel.removeEventListener("keydown", handler4);
  }
  function createModal(config = {}) {
    const steps = config.steps ?? [];
    const size2 = config.size ?? "lg";
    const onOpen = config.onOpen ?? null;
    const onClose = config.onClose ?? null;
    let previouslyFocused = null;
    let trapTeardown = null;
    return {
      modalOpen: false,
      modalStep: 0,
      modalSteps: steps,
      modalSize: size2,
      modalTitle: config.title ?? "",
      openModal() {
        this.modalStep = 0;
        this.modalOpen = true;
        previouslyFocused = document.activeElement;
        if (onOpen) {
          onOpen.call(this);
        }
        const self = this;
        this.$nextTick?.(() => {
          if (self.$el) {
            self.$el.dispatchEvent(new CustomEvent("nebula-modal:open", { bubbles: true }));
          }
          const panel = self.$el?.querySelector('[x-show="modalOpen"]');
          const focusTarget = panel?.querySelector(
            'input, select, textarea, button, [tabindex]:not([tabindex^="-"])'
          );
          if (focusTarget) focusTarget.focus();
          if (panel && trapTeardown === null) {
            trapTeardown = installFocusTrap(panel);
          }
        });
      },
      closeModal() {
        this.modalOpen = false;
        if (trapTeardown) {
          trapTeardown();
          trapTeardown = null;
        }
        if (onClose) {
          onClose.call(this);
        }
        if (this.$el) {
          this.$el.dispatchEvent(new CustomEvent("nebula-modal:close", { bubbles: true }));
        }
        const previous = previouslyFocused;
        previouslyFocused = null;
        if (previous && typeof previous.focus === "function") {
          requestAnimationFrame(() => {
            if (document.contains(previous)) previous.focus();
          });
        }
      },
      nextModalStep(canProceed) {
        if (canProceed && !canProceed.call(this)) return;
        if (this.modalStep < this.modalSteps.length - 1) {
          this.modalStep++;
        }
      },
      prevModalStep() {
        if (this.modalStep > 0) {
          this.modalStep--;
        }
      },
      goToModalStep(index) {
        if (index >= 0 && index < this.modalSteps.length) {
          this.modalStep = index;
        }
      },
      get modalSizeClass() {
        return sizeClasses[this.modalSize] ?? sizeClasses.lg;
      },
      get isFirstModalStep() {
        return this.modalStep === 0;
      },
      get isLastModalStep() {
        return this.modalSteps.length === 0 || this.modalStep === this.modalSteps.length - 1;
      },
      get hasModalSteps() {
        return this.modalSteps.length > 0;
      },
      get currentModalStepLabel() {
        if (this.modalSteps.length === 0) return "";
        return this.modalSteps[this.modalStep] ?? "";
      },
      get modalStepCount() {
        return this.modalSteps.length;
      }
    };
  }
  function installModal() {
    const current = window.Nebula ?? {};
    window.Nebula = Object.assign({}, current, {
      modal: (config) => createModal(config ?? {})
    });
  }

  // ts/confirm.ts
  function resolveOptions(options) {
    const danger = options.danger === true;
    return {
      title: options.title,
      message: options.message,
      confirmText: options.confirmText ?? (danger ? "Delete" : "Confirm"),
      cancelText: options.cancelText ?? "Cancel",
      danger
    };
  }
  function createConfirmStore() {
    let resolver = null;
    const store2 = {
      open: false,
      options: null,
      show(options) {
        if (this.open) {
          return Promise.resolve(false);
        }
        this.options = resolveOptions(options);
        this.open = true;
        return new Promise((resolve) => {
          resolver = resolve;
        });
      },
      confirm() {
        this.open = false;
        this.options = null;
        const r = resolver;
        resolver = null;
        r?.(true);
      },
      cancel() {
        this.open = false;
        this.options = null;
        const r = resolver;
        resolver = null;
        r?.(false);
      }
    };
    return store2;
  }
  function installConfirm() {
    const store2 = createConfirmStore();
    let trapTeardown = null;
    let previousFocus = null;
    function findPanel() {
      const cancel = document.querySelector("[data-nebula-confirm-cancel]");
      return cancel?.closest('[role="dialog"]') ?? cancel?.parentElement ?? null;
    }
    function onOpen() {
      previousFocus = document.activeElement;
      const panel = findPanel();
      if (!panel) {
        return;
      }
      trapTeardown = installFocusTrap(panel);
      const focusTarget = store2.options?.danger ? panel.querySelector("[data-nebula-confirm-cancel]") : panel.querySelector("[data-nebula-confirm-confirm]");
      queueMicrotask(() => focusTarget?.focus());
    }
    function onClose() {
      trapTeardown?.();
      trapTeardown = null;
      previousFocus?.focus();
      previousFocus = null;
    }
    const baseShow = store2.show;
    const baseConfirm = store2.confirm;
    const baseCancel = store2.cancel;
    store2.show = function(options) {
      const wasOpen = this.open;
      const p = baseShow.call(this, options);
      if (!wasOpen && this.open) {
        onOpen();
      }
      return p;
    };
    store2.confirm = function() {
      const wasOpen = this.open;
      baseConfirm.call(this);
      if (wasOpen) {
        onClose();
      }
    };
    store2.cancel = function() {
      const wasOpen = this.open;
      baseCancel.call(this);
      if (wasOpen) {
        onClose();
      }
    };
    document.addEventListener("alpine:init", () => {
      window.Alpine.store("nebulaConfirm", store2);
    });
    window.Nebula = Object.assign({}, window.Nebula ?? {}, {
      confirm(options) {
        const reactive3 = window.Alpine?.store("nebulaConfirm") ?? store2;
        return reactive3.show(options);
      }
    });
    return store2;
  }

  // ts/models.ts
  function createModelStore() {
    return {
      _models: {},
      _counter: 0,
      register(name, model) {
        this._models[name] = model;
      },
      unregister(name) {
        delete this._models[name];
      },
      nextId() {
        return "__model_" + String(++this._counter);
      },
      get(name) {
        return this._models[name] ?? null;
      },
      validateAll() {
        let valid = true;
        for (const model of Object.values(this._models)) {
          if (typeof model.validate === "function" && !model.validate()) {
            valid = false;
          }
        }
        return valid;
      },
      serializeAll(form) {
        form.querySelectorAll("[data-nebula-model]").forEach((el) => el.remove());
        for (const [name, model] of Object.entries(this._models)) {
          if (typeof model.serialize !== "function") continue;
          const pairs = model.serialize();
          if (!Array.isArray(pairs)) continue;
          for (const pair of pairs) {
            if (pair.name === void 0 || pair.name === null) continue;
            const input = document.createElement("input");
            input.type = "hidden";
            input.name = pair.name;
            input.value = pair.value !== void 0 && pair.value !== null ? String(pair.value) : "";
            input.setAttribute("data-nebula-model", name);
            form.appendChild(input);
          }
        }
      },
      debug() {
        const result = {};
        for (const [name, model] of Object.entries(this._models)) {
          if (typeof model.serialize === "function") {
            result[name] = model.serialize();
          }
        }
        return result;
      }
    };
  }
  function registerModelStore() {
    document.addEventListener("alpine:init", () => {
      window.Alpine.store("nebulaModels", createModelStore());
    });
  }

  // ts/rule-editor.ts
  var OPERATORS_BY_INPUT = {
    string: ["==", "!=", ">=", ">", "<=", "<", "{}", "!{}", "()", "!()"],
    numeric: ["==", "!=", ">=", ">", "<=", "<", "()", "!()"],
    date: ["==", ">=", "<="],
    select: ["==", "!=", "<=>"],
    boolean: ["==", "!=", "<=>"],
    multiselect: ["{}", "!{}", "()", "!()"],
    category: ["==", "!=", "()", "!()", "<=>"],
    grid: ["()", "!()"]
  };
  var OPERATOR_LABELS = {
    "==": "is",
    "!=": "is not",
    ">=": "equals or greater than",
    ">": "greater than",
    "<=": "equals or less than",
    "<": "less than",
    "{}": "contains",
    "!{}": "does not contain",
    "()": "is one of",
    "!()": "is not one of",
    "<=>": "is undefined"
  };
  var _moduleIdCounter = 0;
  function _moduleNextId() {
    return "n" + String(++_moduleIdCounter);
  }
  function getInputType(code, attrs) {
    if (!code) return "string";
    const a = attrs.find((x) => x.value === code);
    return a?.inputType ?? "string";
  }
  function getOperatorsForType(type) {
    return (OPERATORS_BY_INPUT[type] ?? OPERATORS_BY_INPUT.string).map((c) => ({
      value: c,
      label: OPERATOR_LABELS[c] ?? c
    }));
  }
  function buildNode(data2, attrs, nextIdFn = _moduleNextId) {
    const isCombine = Array.isArray(data2.conditions);
    return {
      id: nextIdFn(),
      type: isCombine ? "combine" : "leaf",
      className: data2.type ?? "",
      aggregator: data2.aggregator ?? "all",
      attribute: data2.attribute ?? null,
      operator: data2.operator ?? "==",
      value: data2.value != null ? String(data2.value) : "",
      inputType: isCombine ? "string" : getInputType(data2.attribute, attrs),
      children: isCombine ? (data2.conditions ?? []).map((c) => buildNode(c, attrs, nextIdFn)) : []
    };
  }
  function serializeNode(node, path, prefix2, pairs) {
    const p = prefix2 + "[conditions][" + path + "]";
    pairs.push({ name: p + "[type]", value: node.className });
    if (node.type === "combine") {
      pairs.push({ name: p + "[aggregator]", value: node.aggregator });
      pairs.push({ name: p + "[value]", value: "1" });
      node.children.forEach((child, i) => {
        serializeNode(child, path + "--" + String(i + 1), prefix2, pairs);
      });
    } else {
      pairs.push({ name: p + "[attribute]", value: node.attribute ?? "" });
      pairs.push({ name: p + "[operator]", value: node.operator });
      pairs.push({ name: p + "[value]", value: node.value });
    }
  }
  function findNodeByPath(root, pathStr) {
    if (pathStr === "root") return root;
    const parts = pathStr.split(".").filter((p) => p !== "root");
    let node = root;
    for (const part of parts) {
      const m = part.match(/^children\[(\d+)\]$/);
      if (m && node && node.children) {
        const idx = parseInt(m[1] ?? "0", 10);
        node = node.children[idx] ?? null;
      } else {
        return null;
      }
    }
    return node;
  }
  var _escEl = document.createElement("div");
  function esc(str) {
    _escEl.textContent = str == null ? "" : String(str);
    return _escEl.innerHTML;
  }
  function defaultCombineClass(ruleType) {
    return ruleType === "sales" ? "Magento\\SalesRule\\Model\\Rule\\Condition\\Combine" : "Magento\\CatalogRule\\Model\\Rule\\Condition\\Combine";
  }
  function createRuleEditor(config = {}) {
    let idCounter = 0;
    function nextId() {
      return "n" + String(++idCounter);
    }
    const availableAttributes = config.availableAttributes ?? [];
    const conditionTypes = config.conditionTypes ?? [];
    const ruleType = config.ruleType ?? "catalog";
    const fieldPrefix = config.fieldPrefix ?? "rule";
    const state = {
      rootNode: null,
      availableAttributes,
      conditionTypes,
      ruleType,
      fieldPrefix,
      init() {
        if (config.conditions && config.conditions.type) {
          this.rootNode = buildNode(config.conditions, this.availableAttributes, nextId);
        } else {
          this.rootNode = {
            id: nextId(),
            type: "combine",
            className: defaultCombineClass(this.ruleType),
            aggregator: "all",
            attribute: null,
            operator: "==",
            value: "1",
            inputType: "string",
            children: []
          };
        }
        const models = window.Alpine?.store("nebulaModels");
        if (config.registerModel !== false && models) {
          models.register("section:conditions", {
            serialize: () => this.serialize()
          });
        }
        const self = this;
        this.$nextTick?.(() => {
          const el = self.$el;
          if (!el) return;
          el.addEventListener("change", (e) => self._handleChange(e));
          el.addEventListener("click", (e) => self._handleClick(e));
          el.addEventListener("input", (e) => self._handleInput(e));
        });
      },
      serialize() {
        const pairs = [];
        if (this.rootNode) serializeNode(this.rootNode, "1", this.fieldPrefix, pairs);
        return pairs;
      },
      _rerender() {
        this.rootNode = JSON.parse(JSON.stringify(this.rootNode));
      },
      _handleClick(e) {
        if (!this.rootNode) return;
        const target = e.target;
        if (!target) return;
        const btn = target.closest("[data-action]");
        if (!btn) return;
        const action = btn.dataset["action"];
        const path = btn.dataset["path"] ?? "root";
        const parent = findNodeByPath(this.rootNode, path === "root" ? "root" : path);
        if (action === "add-condition" && parent) {
          const cls = this.conditionTypes.length ? this.conditionTypes[0].value : "";
          parent.children.push({
            id: nextId(),
            type: "leaf",
            className: cls,
            aggregator: "all",
            attribute: null,
            operator: "==",
            value: "",
            inputType: "string",
            children: []
          });
          this._rerender();
        } else if (action === "add-group" && parent) {
          parent.children.push({
            id: nextId(),
            type: "combine",
            className: defaultCombineClass(this.ruleType),
            aggregator: "all",
            attribute: null,
            operator: "==",
            value: "1",
            inputType: "string",
            children: []
          });
          this._rerender();
        } else if (action === "remove") {
          const parentPath = btn.dataset["parent"];
          const idx = parseInt(btn.dataset["index"] ?? "NaN", 10);
          if (!parentPath) return;
          const parentNode = findNodeByPath(
            this.rootNode,
            parentPath === "root" ? "root" : parentPath
          );
          if (parentNode && !isNaN(idx)) {
            parentNode.children.splice(idx, 1);
            this._rerender();
          }
        }
      },
      _handleChange(e) {
        if (!this.rootNode) return;
        const el = e.target;
        if (!el) return;
        const role = el.dataset["role"];
        if (!role) return;
        const path = el.dataset["path"];
        if (!path) return;
        const node = findNodeByPath(this.rootNode, path === "root" ? "root" : path);
        if (!node) return;
        if (role === "aggregator") {
          node.aggregator = el.value === "any" ? "any" : "all";
          this._rerender();
        } else if (role === "attribute") {
          node.attribute = el.value;
          node.inputType = getInputType(el.value, this.availableAttributes);
          const attrDef = this.availableAttributes.find((a) => a.value === el.value);
          if (attrDef && attrDef.conditionClass) {
            node.className = attrDef.conditionClass;
          }
          const ops = getOperatorsForType(node.inputType);
          node.operator = ops.length ? ops[0].value : "==";
          node.value = "";
          this._rerender();
        } else if (role === "operator") {
          node.operator = el.value;
          if (el.value === "<=>") node.value = "";
          this._rerender();
        } else if (role === "value") {
          if (el instanceof HTMLSelectElement && el.multiple) {
            node.value = Array.from(el.selectedOptions).map((o) => o.value).join(",");
          } else {
            node.value = el.value;
          }
        }
      },
      _handleInput(e) {
        if (!this.rootNode) return;
        const el = e.target;
        if (!el) return;
        if (el.dataset["role"] === "value") {
          const path = el.dataset["path"];
          if (!path) return;
          const node = findNodeByPath(this.rootNode, path === "root" ? "root" : path);
          if (node) node.value = el.value;
        }
      },
      renderTree() {
        if (!this.rootNode) return '<div class="text-sm text-gray-400">Loading conditions...</div>';
        return this._renderCombine(this.rootNode, 0, "root");
      },
      _renderCombine(node, depth, path) {
        const indent = depth > 0 ? "ml-6 mt-2" : "";
        const bg = depth === 0 ? "bg-gray-50 border-gray-200" : "bg-indigo-50/30 border-indigo-200/50";
        let h = '<div class="' + indent + " p-4 rounded-lg border " + bg + ' space-y-2">';
        h += '<div class="flex items-center gap-2 text-sm flex-wrap">';
        h += '<span class="font-medium text-gray-700">If</span>';
        h += '<select data-role="aggregator" data-path="' + esc(path) + '" class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs font-semibold focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none"><option value="all"' + (node.aggregator === "all" ? " selected" : "") + '>ALL</option><option value="any"' + (node.aggregator === "any" ? " selected" : "") + ">ANY</option></select>";
        h += '<span class="text-gray-600">of these conditions are</span>';
        h += '<span class="font-semibold text-gray-900">TRUE</span>';
        h += '<span class="text-gray-400">:</span>';
        if (depth > 0) {
          const parentPath = path.substring(0, path.lastIndexOf("."));
          const idxMatch = path.match(/\[(\d+)\]$/);
          const idx = idxMatch ? idxMatch[1] : void 0;
          if (idx !== void 0) {
            h += '<button type="button" data-action="remove" data-parent="' + esc(parentPath) + '" data-index="' + idx + '" class="ml-auto p-1 text-gray-400 hover:text-red-600 transition cursor-pointer" title="Remove group"><svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg></button>';
          }
        }
        h += "</div>";
        for (let i = 0; i < node.children.length; i++) {
          const childPath = path + ".children[" + i + "]";
          const child = node.children[i];
          if (!child) continue;
          if (child.type === "combine") {
            h += this._renderCombine(child, depth + 1, childPath);
          } else {
            h += this._renderLeaf(child, i, path, childPath);
          }
        }
        h += '<div class="flex items-center gap-2 pt-1">';
        h += '<button type="button" data-action="add-condition" data-path="' + esc(path) + '" class="inline-flex items-center gap-1 rounded-md bg-white border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50 transition cursor-pointer"><svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg> Condition</button>';
        h += '<button type="button" data-action="add-group" data-path="' + esc(path) + '" class="inline-flex items-center gap-1 rounded-md bg-white border border-dashed border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-500 hover:bg-gray-50 hover:border-gray-400 transition cursor-pointer"><svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg> Group</button>';
        h += "</div></div>";
        return h;
      },
      _renderLeaf(node, index, parentPath, nodePath) {
        let h = '<div class="ml-6 mt-1 flex items-center gap-2 rounded-md bg-white border border-gray-200 px-3 py-2 text-sm flex-wrap shadow-sm">';
        h += '<select data-role="attribute" data-path="' + esc(nodePath) + '" class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none"><option value="">-- select --</option>';
        this.availableAttributes.forEach((a) => {
          h += '<option value="' + esc(a.value) + '"' + (a.value === node.attribute ? " selected" : "") + ">" + esc(a.label) + "</option>";
        });
        h += "</select>";
        if (node.attribute) {
          const ops = getOperatorsForType(node.inputType);
          h += '<select data-role="operator" data-path="' + esc(nodePath) + '" class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none">';
          ops.forEach((op) => {
            h += '<option value="' + esc(op.value) + '"' + (op.value === node.operator ? " selected" : "") + ">" + esc(op.label) + "</option>";
          });
          h += "</select>";
          if (node.operator !== "<=>") {
            const attr = this.availableAttributes.find((a) => a.value === node.attribute);
            const opts = attr?.options ?? [];
            const mode = this.getValueMode(node);
            if (mode === "select" && opts.length) {
              h += '<select data-role="value" data-path="' + esc(nodePath) + '" class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none"><option value="">--</option>';
              opts.forEach((o) => {
                h += '<option value="' + esc(o.value) + '"' + (String(o.value) === String(node.value) ? " selected" : "") + ">" + esc(o.label) + "</option>";
              });
              h += "</select>";
            } else if (mode === "multiselect" && opts.length) {
              const sel = node.value ? String(node.value).split(",") : [];
              h += '<select multiple data-role="value" data-path="' + esc(nodePath) + '" class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none min-h-[50px]">';
              opts.forEach((o) => {
                h += '<option value="' + esc(o.value) + '"' + (sel.includes(String(o.value)) ? " selected" : "") + ">" + esc(o.label) + "</option>";
              });
              h += "</select>";
            } else {
              h += '<input type="text" data-role="value" data-path="' + esc(nodePath) + '" value="' + esc(node.value) + '" class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none w-36">';
            }
          }
        }
        h += '<button type="button" data-action="remove" data-parent="' + esc(parentPath) + '" data-index="' + String(index) + '" class="ml-auto p-1 text-gray-400 hover:text-red-600 transition cursor-pointer flex-shrink-0" title="Remove"><svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg></button>';
        h += "</div>";
        return h;
      },
      getValueMode(_node) {
        if (_node.operator === "<=>") return "hidden";
        const t = _node.inputType;
        if (t === "category") {
          return _node.operator === "()" || _node.operator === "!()" ? "multiselect" : "select";
        }
        if (t === "select" || t === "boolean") return "select";
        if (t === "multiselect") return "multiselect";
        return "text";
      }
    };
    return state;
  }
  function registerRuleEditor() {
    document.addEventListener("alpine:init", () => {
      window.Alpine.data(
        "nebulaRuleEditor",
        (config = {}) => createRuleEditor(config)
      );
    });
  }

  // ts/toast.ts
  var TOAST_DURATION = 5e3;
  var ANIMATION_DURATION = 300;
  var TYPE_STYLES = {
    success: {
      bg: "#16a34a",
      icon: '<path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>'
    },
    error: {
      bg: "#dc2626",
      icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/>'
    },
    warning: {
      bg: "#d97706",
      icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>'
    },
    notice: {
      bg: "#2563eb",
      icon: '<path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"/>'
    }
  };
  var createToastContainer = () => {
    const existing = document.getElementById("nebula-toast-container");
    if (existing) return existing;
    const container = document.createElement("div");
    container.id = "nebula-toast-container";
    container.setAttribute("role", "region");
    container.setAttribute("aria-label", "Notifications");
    container.style.cssText = "position:fixed;top:0;left:0;right:0;z-index:2147483647;display:flex;flex-direction:column;align-items:center;pointer-events:none;";
    document.body.appendChild(container);
    return container;
  };
  var createToast = (type, message) => {
    const style = TYPE_STYLES[type] ?? TYPE_STYLES.notice;
    const container = createToastContainer();
    const toast = document.createElement("div");
    toast.className = "nebula-toast";
    toast.dataset["type"] = type;
    toast.style.cssText = "pointer-events:auto;width:100%;max-width:600px;margin-top:8px;transform:translateY(-100%);opacity:0;transition:transform " + ANIMATION_DURATION + "ms ease-out, opacity " + ANIMATION_DURATION + "ms ease-out;";
    toast.innerHTML = '<div style="background:' + style.bg + ';color:#f8fafc !important;border-radius:12px;padding:0;overflow:hidden;box-shadow:0 10px 25px -5px rgba(0,0,0,.2),0 8px 10px -6px rgba(0,0,0,.1);">  <div style="display:flex;align-items:center;gap:12px;padding:14px 16px;">    <svg style="width:20px;height:20px;color:#f8fafc !important;flex-shrink:0;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">' + style.icon + '</svg>    <span class="nebula-toast-message" style="flex:1;color:#f8fafc !important;font-size:14px;font-weight:600;line-height:1.4;"></span>    <button class="nebula-toast-pause" title="Pause" style="color:rgba(255,255,255,.7);cursor:pointer;background:none;border:none;padding:2px;display:flex;flex-shrink:0;transition:color .15s;">      <svg style="width:16px;height:16px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25v13.5m-7.5-13.5v13.5"/></svg>    </button>    <button class="nebula-toast-close" title="Close" style="color:rgba(255,255,255,.7);cursor:pointer;background:none;border:none;padding:2px;display:flex;flex-shrink:0;transition:color .15s;">      <svg style="width:16px;height:16px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>    </button>  </div>  <div class="nebula-toast-timer" style="height:3px;background:rgba(255,255,255,.4);border-radius:0 0 12px 12px;">    <div class="nebula-toast-timer-bar" style="height:100%;background:rgba(255,255,255,.8);border-radius:0 0 12px 12px;width:100%;transition:width linear;"></div>  </div></div>';
    const messageEl = toast.querySelector(".nebula-toast-message");
    if (messageEl) {
      messageEl.textContent = message;
    }
    container.appendChild(toast);
    const hoverButtons = toast.querySelectorAll(
      ".nebula-toast-pause, .nebula-toast-close"
    );
    hoverButtons.forEach((btn) => {
      btn.addEventListener("mouseover", () => {
        btn.style.color = "white";
      });
      btn.addEventListener("mouseout", () => {
        btn.style.color = "rgba(255,255,255,.7)";
      });
    });
    const timerBar = toast.querySelector(".nebula-toast-timer-bar");
    let paused = false;
    let remaining = TOAST_DURATION;
    let startTime = Date.now();
    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        toast.style.transform = "translateY(0)";
        toast.style.opacity = "1";
        startTime = Date.now();
      });
    });
    timerBar.style.transitionDuration = TOAST_DURATION + "ms";
    requestAnimationFrame(() => {
      timerBar.style.width = "0%";
    });
    const dismiss = () => {
      toast.style.transform = "translateY(-100%)";
      toast.style.opacity = "0";
      setTimeout(() => {
        if (toast.parentNode) toast.parentNode.removeChild(toast);
      }, ANIMATION_DURATION);
    };
    let timer = setTimeout(dismiss, TOAST_DURATION);
    toast.addEventListener("mouseenter", () => {
      if (!paused) {
        remaining -= Date.now() - startTime;
        clearTimeout(timer);
        timerBar.style.transitionDuration = "0ms";
        timerBar.style.width = remaining / TOAST_DURATION * 100 + "%";
      }
    });
    toast.addEventListener("mouseleave", () => {
      if (!paused) {
        startTime = Date.now();
        timerBar.style.transitionDuration = remaining + "ms";
        requestAnimationFrame(() => {
          timerBar.style.width = "0%";
        });
        timer = setTimeout(dismiss, remaining);
      }
    });
    const pauseBtn = toast.querySelector(".nebula-toast-pause");
    const pauseIcon = '<svg style="width:16px;height:16px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25v13.5m-7.5-13.5v13.5"/></svg>';
    const playIcon = '<svg style="width:16px;height:16px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z"/></svg>';
    pauseBtn.addEventListener("click", (e) => {
      e.stopPropagation();
      if (paused) {
        paused = false;
        pauseBtn.innerHTML = pauseIcon;
        pauseBtn.title = "Pause";
        startTime = Date.now();
        timerBar.style.transitionDuration = remaining + "ms";
        requestAnimationFrame(() => {
          timerBar.style.width = "0%";
        });
        timer = setTimeout(dismiss, remaining);
      } else {
        paused = true;
        pauseBtn.innerHTML = playIcon;
        pauseBtn.title = "Resume";
        remaining -= Date.now() - startTime;
        clearTimeout(timer);
        timerBar.style.transitionDuration = "0ms";
        timerBar.style.width = remaining / TOAST_DURATION * 100 + "%";
      }
    });
    toast.querySelector(".nebula-toast-close").addEventListener("click", (e) => {
      e.stopPropagation();
      clearTimeout(timer);
      dismiss();
    });
    return toast;
  };
  var detectType = (classNames) => {
    if (classNames.indexOf("success") !== -1) return "success";
    if (classNames.indexOf("error") !== -1) return "error";
    if (classNames.indexOf("warning") !== -1) return "warning";
    return "notice";
  };
  var processMessages = () => {
    const wrappers = document.querySelectorAll(
      ".nebula-messages .messages, .page.messages .messages, .messages"
    );
    wrappers.forEach((wrapper) => {
      const msgs = wrapper.querySelectorAll(".message");
      msgs.forEach((msg) => {
        const text = (msg.textContent ?? "").trim();
        if (!text) return;
        const type = detectType(msg.className);
        createToast(type, text);
      });
      const parent = wrapper.closest(".nebula-messages");
      if (parent) {
        parent.style.display = "none";
      } else {
        wrapper.style.display = "none";
      }
    });
  };
  function installToast() {
    const g = window;
    if (g.__nebulaToastInstalled) return;
    g.__nebulaToastInstalled = true;
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", processMessages);
    } else {
      processMessages();
    }
    let scheduled = false;
    const scheduleProcess = () => {
      if (scheduled) return;
      scheduled = true;
      setTimeout(() => {
        scheduled = false;
        processMessages();
      }, 50);
    };
    const observer2 = new MutationObserver((mutations) => {
      for (const mutation of mutations) {
        for (const node of Array.from(mutation.addedNodes)) {
          if (node.nodeType !== 1) continue;
          const el = node;
          if (el.classList?.contains("messages") || el.querySelector?.(".messages")) {
            scheduleProcess();
            return;
          }
        }
      }
    });
    const target = document.querySelector("main, .nebula-content, #anchor-content") ?? document.body;
    observer2.observe(target, { childList: true, subtree: true });
    g.__nebulaToastObserver = observer2;
    window.nebulaToast = (type, message) => {
      createToast(type, message);
    };
  }

  // ts/validate.ts
  var ERROR_CLASS = "nebula-field-error";
  var RULES = {
    required: (value) => {
      if (value === null || value === void 0) return false;
      if (Array.isArray(value)) return value.length > 0;
      return String(value).trim() !== "";
    },
    number: (value) => {
      if (value === "" || value === null || value === void 0) return true;
      const num = parseFloat(String(value));
      return !isNaN(num) && isFinite(value);
    },
    min: (value, param) => {
      if (value === "" || value === null || value === void 0) return true;
      return parseFloat(String(value)) >= parseFloat(String(param));
    },
    max: (value, param) => {
      if (value === "" || value === null || value === void 0) return true;
      return parseFloat(String(value)) <= parseFloat(String(param));
    },
    minLength: (value, param) => {
      if (value === "" || value === null || value === void 0) return true;
      return String(value).length >= parseInt(String(param), 10);
    },
    maxLength: (value, param) => {
      if (value === "" || value === null || value === void 0) return true;
      return String(value).length <= parseInt(String(param), 10);
    },
    email: (value) => {
      if (value === "" || value === null || value === void 0) return true;
      return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value));
    },
    pattern: (value, param) => {
      if (value === "" || value === null || value === void 0) return true;
      return new RegExp(String(param)).test(String(value));
    }
  };
  var MESSAGES = {
    required: "{label} is required",
    number: "{label} must be a number",
    min: "{label} must be at least {param}",
    max: "{label} must be at most {param}",
    minLength: "{label} must be at least {param} characters",
    maxLength: "{label} must be at most {param} characters",
    email: "{label} must be a valid email",
    pattern: "{label} format is invalid"
  };
  var parseRules = (rulesStr) => {
    if (!rulesStr) return [];
    return rulesStr.split("|").map((rule) => {
      const parts = rule.split(":");
      return { name: parts[0] ?? "", param: parts[1] ?? null };
    });
  };
  var getMessage = (ruleName, label, param) => {
    const msg = MESSAGES[ruleName] ?? "{label} is invalid";
    return msg.replace("{label}", label).replace("{param}", param ?? "");
  };
  var clearFieldError = (field) => {
    field.classList.remove(ERROR_CLASS);
    const existing = field.parentNode?.querySelector(".nebula-field-error-msg");
    if (existing) existing.remove();
  };
  var setFieldError = (field, message) => {
    field.classList.add(ERROR_CLASS);
    const msgEl = document.createElement("div");
    msgEl.className = "nebula-field-error-msg";
    msgEl.textContent = message;
    const existing = field.parentNode?.querySelector(".nebula-field-error-msg");
    if (existing) existing.remove();
    field.parentNode?.appendChild(msgEl);
  };
  var readFieldValue = (field) => {
    if (field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement || field instanceof HTMLSelectElement) {
      return field.value;
    }
    return "";
  };
  var isHiddenOrDisabled = (field) => {
    if (field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement) {
      if (field.disabled) return true;
      if (field instanceof HTMLInputElement && field.type === "hidden") return true;
    }
    const parent = field.closest("[x-show]");
    if (parent && field.offsetParent === null) return true;
    return false;
  };
  var validateField = (field) => {
    const rulesStr = field.getAttribute("data-validate");
    if (!rulesStr) return true;
    const label = field.getAttribute("data-validate-label") ?? field.getAttribute("name") ?? "Field";
    const rules = parseRules(rulesStr);
    const value = readFieldValue(field);
    clearFieldError(field);
    for (const rule of rules) {
      const fn = RULES[rule.name];
      if (fn && !fn(value, rule.param)) {
        setFieldError(field, getMessage(rule.name, label, rule.param));
        return false;
      }
    }
    return true;
  };
  var validateValue = (value, rules) => {
    if (!rules || typeof rules !== "object") return null;
    const label = rules.label ?? "Field";
    const tests = Object.entries(rules);
    for (const [ruleName, param] of tests) {
      if (ruleName === "label") continue;
      const fn = RULES[ruleName];
      if (!fn) continue;
      if (param === true) {
        if (!fn(value, null)) return getMessage(ruleName, label, null);
      } else if (param === false || param === null || param === void 0) {
        continue;
      } else {
        if (!fn(value, param)) return getMessage(ruleName, label, String(param));
      }
    }
    return null;
  };
  var snippetValidators = [];
  var registerSnippetValidator = (fn) => {
    snippetValidators.push(fn);
  };
  var runSnippetValidators = (form) => {
    let errors = [];
    snippetValidators.forEach((fn) => {
      const result = fn(form);
      if (result && result.length) errors = errors.concat(result);
    });
    return errors;
  };
  var validateForm = (form) => {
    const fields = form.querySelectorAll("[data-validate]");
    const errors = [];
    let firstError = null;
    fields.forEach((field) => {
      if (isHiddenOrDisabled(field)) return;
      if (!validateField(field)) {
        errors.push(field);
        if (!firstError) firstError = field;
      }
    });
    errors.push(...runSnippetValidators(form));
    if (errors.length > 0) {
      if (firstError) {
        firstError.scrollIntoView({ behavior: "smooth", block: "center" });
        firstError.focus();
      }
      if (window.nebulaToast) {
        window.nebulaToast(
          "error",
          errors.length + (errors.length === 1 ? " error" : " errors") + " found. Please fix before saving."
        );
      }
    }
    return errors.length === 0;
  };
  var registerRule = (name, fn, msg) => {
    RULES[name] = fn;
    if (msg) MESSAGES[name] = msg;
  };
  function installValidate() {
    const g = window;
    if (g.__nebulaValidateInstalled) return;
    g.__nebulaValidateInstalled = true;
    document.addEventListener(
      "blur",
      (e) => {
        const target = e.target;
        if (target && typeof target.getAttribute === "function" && target.getAttribute("data-validate")) {
          validateField(target);
        }
      },
      true
    );
    document.addEventListener(
      "input",
      (e) => {
        const target = e.target;
        if (target && target.classList && target.classList.contains(ERROR_CLASS)) {
          clearFieldError(target);
        }
      },
      true
    );
    const api = {
      validateField,
      validateForm,
      validateValue,
      registerValidator: registerSnippetValidator,
      clearFieldError,
      setFieldError,
      rule: registerRule
    };
    window.Nebula = Object.assign({}, window.Nebula ?? {}, api);
  }

  // ts/nebula-core.ts
  window.Alpine = module_default;
  module_default.plugin(module_default2);
  installVendorCompat();
  installBaseField();
  installValidate();
  installToast();
  installModal();
  installConfirm();
  registerModelStore();
  registerTextField();
  registerTextareaField();
  registerSelectField();
  registerMultiselectField();
  registerToggleField();
  registerCheckboxField();
  registerNumberField();
  registerDateField();
  registerColorField();
  registerHiddenField();
  registerColumnComponents();
  registerLayoutComponent();
  registerRuleEditor();
  registerErrorComponent();
  registerLoadingComponent();
  registerProductSelector();
  registerSearchableMultiselect();
  registerConfigForm();
  registerDashboardTabs();
  registerMediaSynchronize();
  registerNebulaMenu();
  registerStorePicker();
  registerWidgetWizard();
  function bootSystemConfig() {
    bootConfigDepends();
    bootTestConnection();
    bootImagePreview();
  }
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", bootSystemConfig);
  } else {
    bootSystemConfig();
  }
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => module_default.start());
  } else {
    queueMicrotask(() => module_default.start());
  }
})();
//# sourceMappingURL=nebula-core.js.map
